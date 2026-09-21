<?php

namespace Tests\Feature\Recruitment;

use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use App\Modules\Recruitment\Domain\JobStatus;
use App\Modules\Recruitment\Infrastructure\Persistence\Job;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class JobManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_users_cannot_access_any_job_route(): void
    {
        $organisation = Organisation::query()->create(['name' => 'Protected']);
        $job = $this->createJob($organisation);
        $base = "/api/v1/organisations/{$organisation->getKey()}/jobs";

        $this->getJson($base)->assertUnauthorized();
        $this->postJson($base, $this->payload())->assertUnauthorized();
        $this->getJson("{$base}/{$job->getKey()}")->assertUnauthorized();
        $this->patchJson("{$base}/{$job->getKey()}", ['title' => 'Changed'])->assertUnauthorized();
        foreach (['open', 'close', 'archive'] as $transition) {
            $this->postJson("{$base}/{$job->getKey()}/{$transition}")->assertUnauthorized();
        }
    }

    #[DataProvider('writingRoles')]
    public function test_admin_and_recruiter_create_tenant_owned_drafts(string $role): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, $role);
        $response = $this->actingAs($user, 'web')->postJson(
            "/api/v1/organisations/{$organisation->getKey()}/jobs",
            $this->payload(),
        );

        $response->assertCreated()->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.title', 'Registered Nurse')
            ->assertJsonMissingPath('data.organisation_id');
        $this->assertDatabaseHas('jobs', [
            'id' => $response->json('data.id'),
            'organisation_id' => $organisation->getKey(),
            'status' => 'draft',
        ]);
    }

    public function test_creation_rejects_invalid_and_client_controlled_fields(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');
        $other = Organisation::query()->create(['name' => 'Other']);

        $this->actingAs($user, 'web')->postJson("/api/v1/organisations/{$organisation->getKey()}/jobs", [
            'title' => ' ',
            'opened_at' => '2026-10-02T00:00:00+00:00',
            'closes_at' => '2026-10-01T00:00:00+00:00',
            'status' => 'open',
            'organisation_id' => $other->getKey(),
            'user_id' => 999,
            'role' => 'admin',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'title', 'closes_at', 'status', 'organisation_id', 'user_id', 'role',
        ]);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_optional_fields_and_each_nullable_date_are_supported(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');
        $url = "/api/v1/organisations/{$organisation->getKey()}/jobs";

        $this->actingAs($user, 'web')->postJson($url, [
            'title' => 'Closing date only',
            'closes_at' => '2026-11-01T00:00:00+00:00',
        ])->assertCreated()->assertJsonPath('data.opened_at', null);
        $this->actingAs($user, 'web')->postJson($url, [
            'title' => 'Opening date only',
            'opened_at' => '2026-10-01T00:00:00+00:00',
        ])->assertCreated()->assertJsonPath('data.closes_at', null);
    }

    public function test_deactivated_membership_cannot_create_a_job(): void
    {
        config()->set('app.debug', false);
        $user = User::factory()->create();
        [$organisation, $membership] = $this->createMembership($user, 'admin');
        $membership->update(['deactivated_at' => now()]);

        $this->actingAs($user, 'web')->postJson(
            "/api/v1/organisations/{$organisation->getKey()}/jobs",
            $this->payload(),
        )->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        $this->assertDatabaseCount('jobs', 0);
    }

    #[DataProvider('allRoles')]
    public function test_all_active_roles_can_list_and_view_only_their_tenant_jobs(string $role): void
    {
        config()->set('app.debug', false);
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, $role);
        $other = Organisation::query()->create(['name' => 'Other']);
        $own = $this->createJob($organisation, 'Own job');
        $foreign = $this->createJob($other, 'Foreign job');
        $base = "/api/v1/organisations/{$organisation->getKey()}/jobs";

        $this->actingAs($user, 'web')->getJson($base)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->getKey());
        $this->actingAs($user, 'web')->getJson("{$base}/{$own->getKey()}")->assertOk();
        $this->actingAs($user, 'web')->getJson("{$base}/{$foreign->getKey()}")
            ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
    }

    public function test_missing_cross_tenant_and_deactivated_access_share_404_semantics(): void
    {
        config()->set('app.debug', false);
        $user = User::factory()->create();
        [$organisation, $membership] = $this->createMembership($user, 'admin');
        $other = Organisation::query()->create(['name' => 'Other']);
        $foreign = $this->createJob($other);
        $base = "/api/v1/organisations/{$organisation->getKey()}/jobs";

        $cross = $this->actingAs($user, 'web')->getJson("{$base}/{$foreign->getKey()}")
            ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        $missing = $this->actingAs($user, 'web')->getJson("{$base}/999999")
            ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        self::assertSame($cross->getContent(), $missing->getContent());

        $membership->update(['deactivated_at' => now()]);
        $this->actingAs($user, 'web')->getJson($base)->assertNotFound()->assertExactJson(['message' => 'Not Found']);
    }

    public function test_empty_job_list_has_a_bounded_paginated_shape(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');

        $this->actingAs($user, 'web')->getJson(
            "/api/v1/organisations/{$organisation->getKey()}/jobs",
        )->assertOk()->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('links.prev', null)
            ->assertJsonPath('links.next', null);
    }

    public function test_literal_search_filters_sorting_null_order_and_pagination_are_deterministic(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');
        CarbonImmutable::setTestNow('2026-01-01 00:00:00');
        $literal = $this->createJob($organisation, 'Nurse 100%', 'open', 'Nurse', 'full_time', null);
        $this->createJob($organisation, 'Other nurse', 'open', 'Nurse', 'part_time', '2026-02-01');
        $this->createJob($organisation, 'Doctor', 'closed', 'Doctor', 'full_time', '2026-01-01');
        CarbonImmutable::setTestNow();
        $base = "/api/v1/organisations/{$organisation->getKey()}/jobs";

        $this->actingAs($user, 'web')->getJson("{$base}?search=100%25")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $literal->getKey());
        $this->actingAs($user, 'web')->getJson("{$base}?status=open&occupation=Nurse&employment_type=full_time")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $literal->getKey());
        $this->actingAs($user, 'web')->getJson("{$base}?status=closed")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Doctor');
        $this->actingAs($user, 'web')->getJson("{$base}?occupation=Doctor")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Doctor');
        $this->actingAs($user, 'web')->getJson("{$base}?employment_type=part_time")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Other nurse');
        $this->actingAs($user, 'web')->getJson("{$base}?sort=opened_at&direction=asc&per_page=2")
            ->assertOk()->assertJsonPath('data.0.title', 'Doctor')->assertJsonPath('data.1.title', 'Other nurse')
            ->assertJsonPath('meta.last_page', 2);
        $this->actingAs($user, 'web')->getJson("{$base}?sort=opened_at&direction=asc&per_page=2&page=2")
            ->assertOk()->assertJsonPath('data.0.id', $literal->getKey());
        $this->actingAs($user, 'web')->getJson("{$base}?sort=opened_at&direction=desc")
            ->assertOk()->assertJsonPath('data.0.title', 'Other nurse')
            ->assertJsonPath('data.1.title', 'Doctor')->assertJsonPath('data.2.id', $literal->getKey());
        $this->actingAs($user, 'web')->getJson("{$base}?sort=created_at&direction=asc")
            ->assertOk()->assertJsonPath('data.0.id', $literal->getKey())
            ->assertJsonPath('data.2.title', 'Doctor');
        $this->actingAs($user, 'web')->getJson("{$base}?sort=created_at&direction=desc")
            ->assertOk()->assertJsonPath('data.0.title', 'Doctor')
            ->assertJsonPath('data.2.id', $literal->getKey());
    }

    public function test_invalid_and_unknown_list_parameters_are_rejected(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');
        $this->actingAs($user, 'web')->getJson(
            "/api/v1/organisations/{$organisation->getKey()}/jobs?status=live&sort=title&direction=sideways&page=0&per_page=101&role=admin",
        )->assertUnprocessable()->assertJsonValidationErrors(['status', 'sort', 'direction', 'page', 'per_page', 'query']);
    }

    public function test_list_and_detail_job_query_counts_are_fixed(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');
        $job = $this->createJob($organisation);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user, 'web')->getJson(
            "/api/v1/organisations/{$organisation->getKey()}/jobs",
        )->assertOk();
        $listQueries = DB::getQueryLog();
        DB::flushQueryLog();
        $this->actingAs($user, 'web')->getJson(
            "/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}",
        )->assertOk();
        $detailQueries = DB::getQueryLog();
        DB::disableQueryLog();

        self::assertCount(2, array_filter(
            $listQueries,
            static fn (array $query): bool => str_contains($query['query'], 'from "jobs"'),
        ));
        self::assertCount(1, array_filter(
            $detailQueries,
            static fn (array $query): bool => str_contains($query['query'], 'from "jobs"'),
        ));
    }

    #[DataProvider('writingRoles')]
    public function test_admin_and_recruiter_update_profile_fields_but_cannot_change_status(string $role): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, $role);
        $job = $this->createJob($organisation);
        $url = "/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}";

        $this->actingAs($user, 'web')->patchJson($url, ['title' => 'Updated', 'location' => 'Osaka'])
            ->assertOk()->assertJsonPath('data.title', 'Updated')->assertJsonPath('data.status', 'draft');
        $this->actingAs($user, 'web')->patchJson($url, ['status' => 'open', 'organisation_id' => 999])
            ->assertUnprocessable()->assertJsonValidationErrors(['status', 'organisation_id']);
        $this->assertDatabaseHas('jobs', ['id' => $job->getKey(), 'status' => 'draft', 'organisation_id' => $organisation->getKey()]);
    }

    public function test_partial_date_update_validates_against_persisted_date(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');
        $job = $this->createJob($organisation, openedAt: '2026-10-02');

        $this->actingAs($user, 'web')->patchJson(
            "/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}",
            ['closes_at' => '2026-10-01T00:00:00+00:00'],
        )->assertUnprocessable()->assertJsonValidationErrors('closes_at');
        $this->assertDatabaseHas('jobs', ['id' => $job->getKey(), 'closes_at' => null]);
    }

    public function test_hiring_manager_cannot_write_or_transition_and_data_is_unchanged(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'hiring_manager');
        $job = $this->createJob($organisation);
        $base = "/api/v1/organisations/{$organisation->getKey()}/jobs";

        $this->actingAs($user, 'web')->postJson($base, $this->payload())->assertForbidden();
        $this->actingAs($user, 'web')->patchJson("{$base}/{$job->getKey()}", ['title' => 'Forbidden'])->assertForbidden();
        $this->actingAs($user, 'web')->postJson("{$base}/{$job->getKey()}/open")->assertForbidden();
        $this->assertDatabaseHas('jobs', ['id' => $job->getKey(), 'title' => 'Job', 'status' => 'draft']);
    }

    public function test_all_valid_lifecycle_transitions_succeed_without_mutating_profile_dates(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');
        $job = $this->createJob($organisation, openedAt: '2026-10-01', closesAt: '2026-11-01');
        $base = "/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}";

        $this->actingAs($user, 'web')->postJson("{$base}/open")->assertOk()->assertJsonPath('data.status', 'open');
        $this->actingAs($user, 'web')->postJson("{$base}/close")->assertOk()->assertJsonPath('data.status', 'closed');
        $this->actingAs($user, 'web')->postJson("{$base}/open")->assertOk()->assertJsonPath('data.status', 'open');
        $this->actingAs($user, 'web')->postJson("{$base}/archive")->assertOk()->assertJsonPath('data.status', 'archived')
            ->assertJsonPath('data.opened_at', '2026-10-01T00:00:00+00:00')
            ->assertJsonPath('data.closes_at', '2026-11-01T00:00:00+00:00');

        $draft = $this->createJob($organisation);
        $this->actingAs($user, 'web')->postJson(
            "/api/v1/organisations/{$organisation->getKey()}/jobs/{$draft->getKey()}/archive",
        )->assertOk()->assertJsonPath('data.status', 'archived');

        $closed = $this->createJob($organisation, status: 'closed');
        $this->actingAs($user, 'web')->postJson(
            "/api/v1/organisations/{$organisation->getKey()}/jobs/{$closed->getKey()}/archive",
        )->assertOk()->assertJsonPath('data.status', 'archived');
    }

    #[DataProvider('invalidTransitions')]
    public function test_invalid_transitions_return_stable_409_and_leave_status_unchanged(string $current, string $action): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'recruiter');
        $job = $this->createJob($organisation, status: $current);

        $this->actingAs($user, 'web')->postJson(
            "/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}/{$action}",
        )->assertConflict()->assertExactJson(['message' => 'The requested job status transition is not allowed.']);
        $this->assertDatabaseHas('jobs', ['id' => $job->getKey(), 'status' => $current]);
    }

    public function test_second_transition_after_state_change_is_safely_rejected(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');
        $job = $this->createJob($organisation, status: 'open');
        $url = "/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}/close";
        $this->actingAs($user, 'web')->postJson($url)->assertOk();
        $this->actingAs($user, 'web')->postJson($url)->assertConflict();
        $this->assertDatabaseHas('jobs', ['id' => $job->getKey(), 'status' => 'closed']);
    }

    public function test_cross_tenant_updates_and_transitions_cannot_mutate_a_job(): void
    {
        config()->set('app.debug', false);
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');
        $other = Organisation::query()->create(['name' => 'Other']);
        $job = $this->createJob($other);
        $base = "/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}";

        $this->actingAs($user, 'web')->patchJson($base, ['title' => 'Attempted'])->assertNotFound();
        $this->actingAs($user, 'web')->postJson("{$base}/open")->assertNotFound();
        $this->assertDatabaseHas('jobs', ['id' => $job->getKey(), 'title' => 'Job', 'status' => 'draft']);
    }

    public function test_stateful_job_writes_require_csrf(): void
    {
        $this->app->instance('env', 'local');
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');
        $job = $this->createJob($organisation);
        $base = "/api/v1/organisations/{$organisation->getKey()}/jobs";

        $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost:3000')->postJson($base, $this->payload())->assertStatus(419);
        $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost:3000')->patchJson("{$base}/{$job->getKey()}", ['title' => 'No'])->assertStatus(419);
        $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost:3000')->postJson("{$base}/{$job->getKey()}/open")->assertStatus(419);
    }

    /** @return array<string, string|null> */
    private function payload(): array
    {
        return [
            'title' => 'Registered Nurse', 'occupation' => 'Nurse', 'location' => 'Tokyo',
            'employment_type' => 'full_time', 'description' => 'Ward role.',
            'opened_at' => '2026-10-01T00:00:00+00:00', 'closes_at' => '2026-11-01T00:00:00+00:00',
        ];
    }

    /** @return array{Organisation, OrganisationMembership} */
    private function createMembership(User $user, string $role): array
    {
        $organisation = Organisation::query()->create(['name' => 'Test Organisation']);
        $membership = OrganisationMembership::query()->create([
            'organisation_id' => $organisation->getKey(), 'user_id' => $user->getKey(), 'role' => $role,
        ]);

        return [$organisation, $membership];
    }

    private function createJob(
        Organisation $organisation,
        string $title = 'Job',
        string $status = 'draft',
        ?string $occupation = null,
        ?string $employmentType = null,
        ?string $openedAt = null,
        ?string $closesAt = null,
    ): Job {
        return Job::query()->create([
            'organisation_id' => $organisation->getKey(), 'title' => $title, 'status' => JobStatus::from($status),
            'occupation' => $occupation, 'employment_type' => $employmentType,
            'opened_at' => $openedAt, 'closes_at' => $closesAt,
        ]);
    }

    /** @return array<string, array{string}> */
    public static function allRoles(): array
    {
        return ['admin' => ['admin'], 'recruiter' => ['recruiter'], 'hiring manager' => ['hiring_manager']];
    }

    /** @return array<string, array{string}> */
    public static function writingRoles(): array
    {
        return ['admin' => ['admin'], 'recruiter' => ['recruiter']];
    }

    /** @return array<string, array{string, string}> */
    public static function invalidTransitions(): array
    {
        return [
            'draft close' => ['draft', 'close'], 'draft draft-equivalent open twice' => ['open', 'open'],
            'closed close' => ['closed', 'close'], 'archived open' => ['archived', 'open'],
            'archived close' => ['archived', 'close'], 'archived archive' => ['archived', 'archive'],
        ];
    }
}
