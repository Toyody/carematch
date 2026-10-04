<?php

namespace Tests\Feature\Audit;

use App\Modules\Audit\Application\Contracts\AuditRecorder;
use App\Modules\Audit\Application\Data\AuditEvent;
use App\Modules\Candidate\Infrastructure\Persistence\Candidate;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_tenant_events_newest_first_with_filters_and_batched_actor_details(): void
    {
        [$organisation, $admin] = $this->tenant('admin', 'Audit Admin');
        [$otherOrganisation, $otherAdmin] = $this->tenant('admin', 'Other Admin');
        $recorder = $this->app->make(AuditRecorder::class);
        $recorder->record(new AuditEvent((int) $organisation->getKey(), (int) $admin->getKey(), 'candidate.created', 'candidate', 10, [], new DateTimeImmutable('2026-01-01T10:00:00Z')));
        $recorder->record(new AuditEvent((int) $organisation->getKey(), (int) $admin->getKey(), 'candidate.updated', 'candidate', 10, ['changed_fields' => ['occupation']], new DateTimeImmutable('2026-01-02T10:00:00Z')));
        $recorder->record(new AuditEvent((int) $otherOrganisation->getKey(), (int) $otherAdmin->getKey(), 'candidate.updated', 'candidate', 99));

        $url = "/api/v1/organisations/{$organisation->getKey()}/audit-events?event_type=candidate.updated&subject_type=candidate&subject_id=10&actor_user_id={$admin->getKey()}&per_page=1";
        $this->actingAs($admin, 'web')->getJson($url)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event_type', 'candidate.updated')
            ->assertJsonPath('data.0.subject.type', 'candidate')
            ->assertJsonPath('data.0.subject.id', 10)
            ->assertJsonPath('data.0.actor.name', 'Audit Admin')
            ->assertJsonPath('data.0.metadata.changed_fields.0', 'occupation')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissing(['organisation_id' => $otherOrganisation->getKey()]);

        $this->actingAs($admin, 'web')->getJson("/api/v1/organisations/{$organisation->getKey()}/audit-events")
            ->assertOk()
            ->assertJsonPath('data.0.event_type', 'candidate.updated')
            ->assertJsonPath('data.1.event_type', 'candidate.created');
    }

    public function test_audit_read_requires_authentication_active_tenant_and_admin_role(): void
    {
        [$organisation, $admin] = $this->tenant('admin');
        [, $recruiter] = $this->membership($organisation, 'recruiter');
        [, $manager] = $this->membership($organisation, 'hiring_manager');
        $url = "/api/v1/organisations/{$organisation->getKey()}/audit-events";

        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($recruiter, 'web')->getJson($url)->assertForbidden();
        $this->actingAs($manager, 'web')->getJson($url)->assertForbidden();
    }

    public function test_cross_tenant_audit_read_is_not_found(): void
    {
        [$organisation] = $this->tenant('admin');
        [, $outsider] = $this->tenant('admin');

        $this->actingAs($outsider, 'web')
            ->getJson("/api/v1/organisations/{$organisation->getKey()}/audit-events")
            ->assertNotFound();
    }

    public function test_deactivated_tenant_audit_read_is_not_found(): void
    {
        [$organisation, $admin, $membership] = $this->tenant('admin');
        $membership->update(['deactivated_at' => now()]);

        $this->actingAs($admin, 'web')
            ->getJson("/api/v1/organisations/{$organisation->getKey()}/audit-events")
            ->assertNotFound();
    }

    public function test_historical_deactivated_actor_remains_readable_by_a_current_admin(): void
    {
        [$organisation, $historicalActor, $membership] = $this->tenant('admin');
        [, $currentAdmin] = $this->membership($organisation, 'admin');
        $this->app->make(AuditRecorder::class)->record(new AuditEvent(
            (int) $organisation->getKey(), (int) $historicalActor->getKey(), 'candidate.created', 'candidate', 1,
        ));
        $membership->update(['deactivated_at' => now()]);

        $this->actingAs($currentAdmin, 'web')->getJson("/api/v1/organisations/{$organisation->getKey()}/audit-events")
            ->assertOk()->assertJsonPath('data.0.actor.id', $historicalActor->getKey());
    }

    public function test_candidate_mutations_record_trusted_actor_safe_metadata_and_omit_no_op_updates(): void
    {
        [$organisation, $admin] = $this->tenant('admin');
        $base = "/api/v1/organisations/{$organisation->getKey()}/candidates";
        $created = $this->actingAs($admin, 'web')->postJson($base, [
            'first_name' => 'Synthetic', 'last_name' => 'Candidate', 'notes' => 'private initial note',
        ])->assertCreated()->json('data');
        $candidateId = (int) $created['id'];

        $this->actingAs($admin, 'web')->patchJson("{$base}/{$candidateId}", [
            'occupation' => 'Registered Nurse', 'notes' => 'private changed note',
            'latitude' => -37.8136, 'longitude' => 144.9631,
        ])->assertOk();
        $this->actingAs($admin, 'web')->patchJson("{$base}/{$candidateId}", [
            'occupation' => 'Registered Nurse', 'notes' => 'private changed note',
            'latitude' => -37.8136, 'longitude' => 144.9631,
        ])->assertOk();
        $this->actingAs($admin, 'web')->postJson($base, [
            'first_name' => 'Invalid without a last name',
        ])->assertUnprocessable();

        $events = DB::table('audit_events')->where('subject_type', 'candidate')->where('subject_id', $candidateId)->orderBy('id')->get();
        self::assertCount(2, $events);
        self::assertSame(2, DB::table('audit_events')->count());
        self::assertSame(['candidate.created', 'candidate.updated'], $events->pluck('event_type')->all());
        self::assertSame((int) $organisation->getKey(), (int) $events[1]->organisation_id);
        self::assertSame((int) $admin->getKey(), (int) $events[1]->actor_user_id);
        $metadata = json_decode((string) $events[1]->metadata, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['occupation', 'notes', 'latitude', 'longitude'], $metadata['changed_fields']);
        self::assertStringNotContainsString('private changed note', (string) $events[1]->metadata);
        self::assertStringNotContainsString('-37.8136', (string) $events[1]->metadata);
        self::assertStringNotContainsString('144.9631', (string) $events[1]->metadata);
    }

    public function test_audit_write_failure_rolls_back_the_business_mutation(): void
    {
        [$organisation, $admin] = $this->tenant('admin');
        $this->app->bind(AuditRecorder::class, static fn () => new class implements AuditRecorder
        {
            public function record(AuditEvent $event): void
            {
                throw new RuntimeException('forced audit failure');
            }
        });

        $this->actingAs($admin, 'web')->postJson("/api/v1/organisations/{$organisation->getKey()}/candidates", [
            'first_name' => 'Rollback', 'last_name' => 'Candidate',
        ])->assertInternalServerError();

        self::assertFalse(Candidate::query()->where('first_name', 'Rollback')->exists());
    }

    public function test_postgresql_trigger_rejects_audit_event_updates(): void
    {
        [$organisation, $admin] = $this->tenant('admin');
        $recorder = $this->app->make(AuditRecorder::class);
        $recorder->record(new AuditEvent((int) $organisation->getKey(), (int) $admin->getKey(), 'candidate.created', 'candidate', 1));
        $id = (int) DB::table('audit_events')->value('id');

        try {
            DB::table('audit_events')->where('id', $id)->update(['event_type' => 'candidate.updated']);
            self::fail('Audit updates must be rejected.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('append-only', $exception->getMessage());
        }
    }

    public function test_postgresql_trigger_rejects_audit_event_deletes(): void
    {
        [$organisation, $admin] = $this->tenant('admin');
        $this->app->make(AuditRecorder::class)->record(new AuditEvent(
            (int) $organisation->getKey(), (int) $admin->getKey(), 'candidate.created', 'candidate', 1,
        ));
        $id = (int) DB::table('audit_events')->value('id');

        try {
            DB::table('audit_events')->where('id', $id)->delete();
            self::fail('Audit deletes must be rejected.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('append-only', $exception->getMessage());
        }
    }

    public function test_postgresql_composite_foreign_key_rejects_an_actor_from_another_tenant(): void
    {
        [$organisation] = $this->tenant('admin');
        [, $outsider] = $this->tenant('admin');

        $this->expectException(QueryException::class);
        $this->app->make(AuditRecorder::class)->record(new AuditEvent(
            (int) $organisation->getKey(), (int) $outsider->getKey(), 'candidate.created', 'candidate', 2,
        ));
    }

    public function test_postgresql_schema_has_descending_tenant_query_indexes(): void
    {
        $definitions = DB::table('pg_indexes')
            ->where('tablename', 'audit_events')
            ->whereIn('indexname', [
                'audit_events_tenant_occurred_index',
                'audit_events_tenant_event_index',
                'audit_events_tenant_actor_index',
            ])
            ->pluck('indexdef', 'indexname');

        self::assertCount(3, $definitions);
        self::assertStringContainsString('occurred_at DESC, id DESC', (string) $definitions['audit_events_tenant_occurred_index']);
        self::assertStringContainsString('event_type, occurred_at DESC, id DESC', (string) $definitions['audit_events_tenant_event_index']);
        self::assertStringContainsString('actor_user_id, occurred_at DESC, id DESC', (string) $definitions['audit_events_tenant_actor_index']);
    }

    public function test_postgresql_rejects_non_object_audit_metadata(): void
    {
        [$organisation, $admin] = $this->tenant('admin');

        $this->expectException(QueryException::class);
        DB::table('audit_events')->insert([
            'organisation_id' => $organisation->getKey(),
            'actor_user_id' => $admin->getKey(),
            'event_type' => 'candidate.created',
            'subject_type' => 'candidate',
            'subject_id' => 1,
            'metadata' => '[]',
            'occurred_at' => now(),
        ]);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        [$organisation, $admin] = $this->tenant('admin');
        $url = "/api/v1/organisations/{$organisation->getKey()}/audit-events";

        $this->actingAs($admin, 'web')->getJson($url.'?unknown=value')->assertUnprocessable();
        $this->actingAs($admin, 'web')->getJson($url.'?event_type=INVALID&per_page=101')->assertUnprocessable();
        $this->actingAs($admin, 'web')->getJson($url.'?occurred_from=2026-02-02&occurred_to=2026-01-01')->assertUnprocessable();
    }

    /** @return array{Organisation, User, OrganisationMembership} */
    private function tenant(string $role, string $name = 'Audit User'): array
    {
        $organisation = Organisation::query()->create(['name' => 'Audit Organisation '.uniqid()]);
        [$membership, $user] = $this->membership($organisation, $role, $name);

        return [$organisation, $user, $membership];
    }

    /** @return array{OrganisationMembership, User} */
    private function membership(Organisation $organisation, string $role, string $name = 'Member'): array
    {
        $user = User::factory()->create(['name' => $name]);
        $membership = OrganisationMembership::query()->create([
            'organisation_id' => $organisation->getKey(), 'user_id' => $user->getKey(), 'role' => $role,
        ]);

        return [$membership, $user];
    }
}
