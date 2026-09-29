<?php

namespace Tests\Feature\Compliance;

use App\Modules\Candidate\Infrastructure\Persistence\Candidate;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use App\Modules\Recruitment\Domain\JobStatus;
use App\Modules\Recruitment\Infrastructure\Persistence\Job;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class QualificationComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_admin_manages_catalogue(): void
    {
        $admin = User::factory()->create();
        [$organisation] = $this->membership($admin, 'admin');
        $url = "/api/v1/organisations/{$organisation->getKey()}/qualifications";

        $created = $this->actingAs($admin, 'web')->postJson($url, [
            'name' => ' First Aid ', 'category' => ' Safety ', 'description' => 'Current certificate',
        ])->assertCreated()->assertJsonPath('data.name', 'First Aid')->assertJsonMissingPath('data.organisation_id');
        $definitionId = $created->json('data.id');

        $this->patchJson("{$url}/{$definitionId}", [
            'name' => 'First Aid', 'category' => 'Safety', 'description' => null, 'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);

    }

    public function test_candidate_credentials_support_renewals(): void
    {
        $admin = User::factory()->create();
        [$organisation] = $this->membership($admin, 'admin');
        $candidate = $this->candidate($organisation);
        $definitionId = $this->definition($organisation, 'CPR');
        $url = "/api/v1/organisations/{$organisation->getKey()}/candidates/{$candidate->getKey()}/qualifications";

        foreach (['2025-01-01', '2027-01-01'] as $expiry) {
            $this->actingAs($admin, 'web')->postJson($url, [
                'qualification_definition_id' => $definitionId,
                'issuer' => 'Synthetic Training Provider',
                'credential_number' => 'SYNTHETIC-'.$expiry,
                'issued_on' => '2024-01-01',
                'expires_on' => $expiry,
            ])->assertCreated();
        }
        $this->assertDatabaseCount('candidate_qualifications', 2);
        $auditMetadata = DB::table('audit_events')
            ->where('event_type', 'candidate_qualification.created')
            ->pluck('metadata')
            ->implode(' ');
        self::assertStringNotContainsString('SYNTHETIC-', $auditMetadata);
    }

    public function test_recruiter_reads_catalogue_and_manages_credentials_and_requirements(): void
    {
        $recruiter = User::factory()->create();
        [$organisation] = $this->membership($recruiter, 'recruiter');
        $candidate = $this->candidate($organisation);
        $job = $this->job($organisation);
        $definitionId = $this->definition($organisation, 'First Aid');
        $base = "/api/v1/organisations/{$organisation->getKey()}";

        $this->actingAs($recruiter, 'web')->getJson("{$base}/qualifications")
            ->assertOk()->assertJsonPath('data.0.id', $definitionId);
        $this->postJson("{$base}/qualifications", ['name' => 'Admin only'])->assertForbidden();
        $this->postJson("{$base}/candidates/{$candidate->getKey()}/qualifications", [
            'qualification_definition_id' => $definitionId,
        ])->assertCreated();
        $this->postJson("{$base}/jobs/{$job->getKey()}/qualification-requirements", [
            'qualification_definition_id' => $definitionId,
        ])->assertCreated();
    }

    public function test_new_routes_require_authentication_and_stateful_writes_require_csrf(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->membership($user, 'admin');
        $candidate = $this->candidate($organisation);
        $job = $this->job($organisation);
        $definitionId = $this->definition($organisation, 'CSRF protected');
        $base = "/api/v1/organisations/{$organisation->getKey()}";

        $this->getJson("{$base}/qualifications")->assertUnauthorized();
        $this->getJson("{$base}/candidates/{$candidate->getKey()}/qualifications")->assertUnauthorized();
        $this->getJson("{$base}/jobs/{$job->getKey()}/qualification-requirements")->assertUnauthorized();
        $this->getJson("{$base}/jobs/{$job->getKey()}/candidates/{$candidate->getKey()}/qualification-coverage")->assertUnauthorized();
        $this->getJson("{$base}/qualification-expiries")->assertUnauthorized();

        $this->app->instance('env', 'local');
        $stateful = $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost:3000');
        $stateful->postJson("{$base}/qualifications", ['name' => 'Blocked'])->assertStatus(419);
        $stateful->postJson("{$base}/candidates/{$candidate->getKey()}/qualifications", [
            'qualification_definition_id' => $definitionId,
        ])->assertStatus(419);
        $stateful->postJson("{$base}/jobs/{$job->getKey()}/qualification-requirements", [
            'qualification_definition_id' => $definitionId,
        ])->assertStatus(419);

        $this->assertDatabaseMissing('qualification_definitions', ['name' => 'Blocked']);
        $this->assertDatabaseCount('candidate_qualifications', 0);
        $this->assertDatabaseCount('job_qualification_requirements', 0);
    }

    public function test_hiring_manager_can_read_but_cannot_mutate_compliance_data(): void
    {
        $manager = User::factory()->create();
        [$organisation] = $this->membership($manager, 'hiring_manager');
        $candidate = $this->candidate($organisation);
        $job = $this->job($organisation);
        $definitionId = $this->definition($organisation, 'CPR');
        $catalogueUrl = "/api/v1/organisations/{$organisation->getKey()}/qualifications";
        $credentialsUrl = "/api/v1/organisations/{$organisation->getKey()}/candidates/{$candidate->getKey()}/qualifications";
        $requirementsUrl = "/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}/qualification-requirements";

        $this->actingAs($manager, 'web')->getJson($catalogueUrl)->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($manager, 'web')->postJson($catalogueUrl, ['name' => 'First Aid'])->assertForbidden();
        $this->actingAs($manager, 'web')->getJson($credentialsUrl)->assertOk();
        $this->actingAs($manager, 'web')->postJson($credentialsUrl, ['qualification_definition_id' => $definitionId])->assertForbidden();
        $this->actingAs($manager, 'web')->getJson($requirementsUrl)->assertOk();
        $this->actingAs($manager, 'web')->postJson($requirementsUrl, ['qualification_definition_id' => $definitionId])->assertForbidden();
        $this->actingAs($manager, 'web')->getJson("/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}/candidates/{$candidate->getKey()}/qualification-coverage")->assertOk();
        $this->actingAs($manager, 'web')->getJson("/api/v1/organisations/{$organisation->getKey()}/qualification-expiries")->assertOk();
    }

    public function test_job_requirements_and_coverage_use_best_current_evidence(): void
    {
        CarbonImmutable::setTestNow('2026-09-28 12:00:00 UTC');
        $admin = User::factory()->create();
        [$organisation] = $this->membership($admin, 'admin');
        $candidate = $this->candidate($organisation);
        $job = $this->job($organisation);
        $firstAid = $this->definition($organisation, 'First Aid');
        $cpr = $this->definition($organisation, 'CPR');
        $requirementsUrl = "/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}/qualification-requirements";

        $this->actingAs($admin, 'web')->postJson($requirementsUrl, ['qualification_definition_id' => $firstAid])->assertCreated();
        $this->postJson($requirementsUrl, ['qualification_definition_id' => $cpr])->assertCreated();
        $this->postJson($requirementsUrl, ['qualification_definition_id' => $firstAid])->assertConflict();

        foreach (['2025-01-01', '2027-01-01'] as $expiry) {
            DB::table('candidate_qualifications')->insert([
                'organisation_id' => $organisation->getKey(), 'candidate_id' => $candidate->getKey(),
                'qualification_definition_id' => $firstAid, 'expires_on' => $expiry,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->getJson("/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}/candidates/{$candidate->getKey()}/qualification-coverage")
            ->assertOk()
            ->assertJsonPath('data.status', 'not_satisfied')
            ->assertJsonPath('data.requirements.0.status', 'missing')
            ->assertJsonPath('data.requirements.1.status', 'valid');
    }

    public function test_expiry_view_is_tenant_scoped_and_uses_date_boundaries(): void
    {
        CarbonImmutable::setTestNow('2026-09-28 12:00:00 UTC');
        $admin = User::factory()->create();
        [$organisation] = $this->membership($admin, 'admin');
        $candidate = $this->candidate($organisation);
        $definition = $this->definition($organisation, 'Registration');
        foreach (['2026-09-27', '2026-10-28', '2026-10-29'] as $date) {
            DB::table('candidate_qualifications')->insert([
                'organisation_id' => $organisation->getKey(), 'candidate_id' => $candidate->getKey(),
                'qualification_definition_id' => $definition, 'expires_on' => $date,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($admin, 'web')->getJson("/api/v1/organisations/{$organisation->getKey()}/qualification-expiries")
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'expired')->assertJsonPath('data.1.status', 'expiring');
    }

    public function test_cross_tenant_relationships_are_rejected_by_postgresql(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->membership($user, 'admin');
        $other = Organisation::query()->create(['name' => 'Other']);
        $candidate = $this->candidate($organisation);
        $otherDefinition = $this->definition($other, 'Other tenant');

        try {
            DB::table('candidate_qualifications')->insert([
                'organisation_id' => $organisation->getKey(), 'candidate_id' => $candidate->getKey(),
                'qualification_definition_id' => $otherDefinition,
                'issued_on' => '2026-10-01', 'expires_on' => '2026-09-01',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            self::fail('Expected PostgreSQL to reject the invalid tenant relationship.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('candidate_qualifications_', $exception->getMessage());
        }

    }

    public function test_cross_tenant_job_requirement_is_rejected_by_postgresql(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->membership($user, 'admin');
        $other = Organisation::query()->create(['name' => 'Other']);
        $otherDefinition = $this->definition($other, 'Other tenant');
        $job = $this->job($organisation);

        try {
            DB::table('job_qualification_requirements')->insert([
                'organisation_id' => $organisation->getKey(), 'job_id' => $job->getKey(),
                'qualification_definition_id' => $otherDefinition,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            self::fail('Expected PostgreSQL to reject the cross-tenant Job requirement.');
        } catch (QueryException $exception) {
            self::assertStringContainsString('job_qualification_requirements_', $exception->getMessage());
        }
    }

    public function test_schema_has_the_required_constraints_and_query_indexes(): void
    {
        $indexes = DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->whereIn('tablename', [
                'qualification_definitions', 'candidate_qualifications', 'job_qualification_requirements',
            ])->pluck('indexname')->all();

        self::assertContains('qualification_definitions_organisation_id_is_active_name_index', $indexes);
        self::assertContains('candidate_qualifications_candidate_definition_index', $indexes);
        self::assertContains('candidate_qualifications_expiry_index', $indexes);
        self::assertContains('job_qualification_requirements_unique', $indexes);
    }

    public function test_issue_date_after_expiry_date_is_rejected_by_postgresql(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->membership($user, 'admin');
        $candidate = $this->candidate($organisation);
        $definition = $this->definition($organisation, 'Date constrained');

        $this->expectException(QueryException::class);
        DB::table('candidate_qualifications')->insert([
            'organisation_id' => $organisation->getKey(), 'candidate_id' => $candidate->getKey(),
            'qualification_definition_id' => $definition,
            'issued_on' => '2026-10-01', 'expires_on' => '2026-09-01',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_inaccessible_tenants_and_cross_tenant_targets_use_safe_not_found_semantics(): void
    {
        config()->set('app.debug', false);
        $user = User::factory()->create();
        [$organisation] = $this->membership($user, 'admin');
        $other = Organisation::query()->create(['name' => 'Other']);
        $otherCandidate = $this->candidate($other);
        $otherDefinition = $this->definition($other, 'Other definition');
        $otherJob = $this->job($other);
        $job = $this->job($organisation);

        $this->actingAs($user, 'web')->getJson("/api/v1/organisations/{$other->getKey()}/qualifications")
            ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        $this->getJson("/api/v1/organisations/{$organisation->getKey()}/candidates/{$otherCandidate->getKey()}/qualifications")
            ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        $this->postJson("/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}/qualification-requirements", [
            'qualification_definition_id' => $otherDefinition,
        ])->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        $this->getJson("/api/v1/organisations/{$organisation->getKey()}/jobs/{$otherJob->getKey()}/qualification-requirements")
            ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        $this->getJson("/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}/candidates/{$otherCandidate->getKey()}/qualification-coverage")
            ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        $this->getJson("/api/v1/organisations/{$other->getKey()}/qualification-expiries")
            ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
    }

    /** @return array{Organisation, OrganisationMembership} */
    private function membership(User $user, string $role): array
    {
        $organisation = Organisation::query()->create(['name' => 'Qualification Test Organisation']);
        $membership = OrganisationMembership::query()->create([
            'organisation_id' => $organisation->getKey(), 'user_id' => $user->getKey(), 'role' => $role,
        ]);

        return [$organisation, $membership];
    }

    private function candidate(Organisation $organisation): Candidate
    {
        return Candidate::query()->create([
            'organisation_id' => $organisation->getKey(), 'first_name' => 'Synthetic', 'last_name' => 'Candidate',
        ]);
    }

    private function job(Organisation $organisation): Job
    {
        return Job::query()->create([
            'organisation_id' => $organisation->getKey(), 'title' => 'Synthetic Role', 'status' => JobStatus::Open,
        ]);
    }

    private function definition(Organisation $organisation, string $name): int
    {
        return (int) DB::table('qualification_definitions')->insertGetId([
            'organisation_id' => $organisation->getKey(), 'name' => $name, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
