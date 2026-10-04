<?php

namespace Tests\Feature\Matching;

use App\Modules\Candidate\Infrastructure\Persistence\Candidate;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Matching\Application\Contracts\CandidateMatchReadModel;
use App\Modules\Matching\Application\Data\MatchCriteria;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use App\Modules\Recruitment\Domain\JobStatus;
use App\Modules\Recruitment\Infrastructure\Persistence\Job;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CandidateMatchingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_matches_use_deterministic_qualification_occupation_distance_and_id_order(): void
    {
        CarbonImmutable::setTestNow('2026-09-28 12:00:00 UTC');
        $user = User::factory()->create();
        [$organisation] = $this->membership($user, 'admin');
        $job = $this->job($organisation, ' Registered Nurse ', -37.8136, 144.9631);
        $definitionId = $this->requirement($organisation, $job);

        $satisfiedMismatch = $this->candidate($organisation, 'Avery', 'Physiotherapist', -37.8137, 144.9632);
        $satisfiedMatchFar = $this->candidate($organisation, 'Blair', 'registered nurse', -38.1499, 144.3617);
        $attentionMatch = $this->candidate($organisation, 'Casey', 'Registered Nurse', -37.8140, 144.9635);
        $notSatisfied = $this->candidate($organisation, 'Devon', null, null, null);
        $this->credential($organisation, $satisfiedMismatch, $definitionId, '2027-01-01');
        $this->credential($organisation, $satisfiedMatchFar, $definitionId, null);
        $this->credential($organisation, $attentionMatch, $definitionId, '2026-10-01');
        DB::table('applications')->insert([
            'organisation_id' => $organisation->getKey(), 'job_id' => $job->getKey(),
            'candidate_id' => $satisfiedMatchFar->getKey(), 'status' => 'screening',
            'applied_at' => now(), 'created_by_user_id' => $user->getKey(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($user, 'web')
            ->getJson("/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}/matches")
            ->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('data.0.candidate.id', $satisfiedMatchFar->getKey())
            ->assertJsonPath('data.0.rank', 1)
            ->assertJsonPath('data.0.qualification.status', 'satisfied')
            ->assertJsonPath('data.0.occupation_status', 'match')
            ->assertJsonPath('data.0.application_status', 'screening')
            ->assertJsonPath('data.1.candidate.id', $satisfiedMismatch->getKey())
            ->assertJsonPath('data.2.candidate.id', $attentionMatch->getKey())
            ->assertJsonPath('data.2.qualification.status', 'attention_required')
            ->assertJsonPath('data.3.candidate.id', $notSatisfied->getKey())
            ->assertJsonPath('data.3.distance_km', null);

        self::assertGreaterThan(60, (float) $response->json('data.0.distance_km'));
        self::assertSame(
            $response->json('data'),
            $this->getJson("/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}/matches")->json('data'),
        );
    }

    public function test_radius_uses_postgis_and_excludes_null_and_far_coordinates(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->membership($user, 'recruiter');
        $job = $this->job($organisation, 'Nurse', -37.8136, 144.9631);
        $near = $this->candidate($organisation, 'Near', 'Nurse', -37.8183, 144.9671);
        $this->candidate($organisation, 'Far', 'Nurse', -38.1499, 144.3617);
        $this->candidate($organisation, 'Unknown', 'Nurse', null, null);

        $response = $this->actingAs($user, 'web')->getJson(
            "/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}/matches?max_distance_km=5",
        )->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.candidate.id', $near->getKey());

        self::assertGreaterThan(0, (float) $response->json('data.0.distance_km'));
        self::assertLessThan(5, (float) $response->json('data.0.distance_km'));
    }

    public function test_radius_requires_job_coordinates_and_query_is_validated(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->membership($user, 'admin');
        $job = $this->job($organisation, 'Nurse', null, null);

        $this->actingAs($user, 'web')
            ->getJson("/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}/matches?max_distance_km=10")
            ->assertUnprocessable()->assertJsonValidationErrors('max_distance_km');
        $this->getJson("/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}/matches?max_distance_km=1001")
            ->assertUnprocessable()->assertJsonValidationErrors('max_distance_km');
    }

    public function test_page_beyond_the_last_page_keeps_the_global_total(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->membership($user, 'admin');
        $job = $this->job($organisation, 'Nurse', -37.8136, 144.9631);
        $this->candidate($organisation, 'Only', 'Nurse', -37.8136, 144.9631);

        $this->actingAs($user, 'web')->getJson(
            "/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}/matches?page=99",
        )->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('meta.current_page', 99);
    }

    public function test_matching_is_tenant_scoped_and_inaccessible_jobs_are_non_disclosing(): void
    {
        config()->set('app.debug', false);
        $user = User::factory()->create();
        [$organisation] = $this->membership($user, 'hiring_manager');
        $job = $this->job($organisation, 'Nurse', -37.8136, 144.9631);
        $own = $this->candidate($organisation, 'Own', 'Nurse', -37.8136, 144.9631);
        $other = Organisation::query()->create(['name' => 'Other tenant']);
        $otherJob = $this->job($other, 'Other', -37.8136, 144.9631);
        $this->candidate($other, 'Secret', 'Nurse', -37.8136, 144.9631);

        $this->actingAs($user, 'web')
            ->getJson("/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}/matches")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.candidate.id', $own->getKey());
        $this->getJson("/api/v1/organisations/{$organisation->getKey()}/jobs/{$otherJob->getKey()}/matches")
            ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        $this->getJson("/api/v1/organisations/{$other->getKey()}/jobs/{$otherJob->getKey()}/matches")
            ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
    }

    public function test_all_active_roles_can_view_and_unauthenticated_requests_cannot(): void
    {
        $organisation = Organisation::query()->create(['name' => 'Roles']);
        $job = $this->job($organisation, null, null, null);
        $this->getJson("/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}/matches")->assertUnauthorized();

        foreach (['admin', 'recruiter', 'hiring_manager'] as $role) {
            $user = User::factory()->create();
            OrganisationMembership::query()->create([
                'organisation_id' => $organisation->getKey(), 'user_id' => $user->getKey(), 'role' => $role,
            ]);
            $this->actingAs($user, 'web')
                ->getJson("/api/v1/organisations/{$organisation->getKey()}/jobs/{$job->getKey()}/matches")
                ->assertOk();
        }

    }

    public function test_postgis_extension_generated_geography_constraints_and_spatial_index_exist(): void
    {
        $organisation = Organisation::query()->create(['name' => 'Spatial']);
        $candidate = $this->candidate($organisation, 'Spatial', 'Nurse', -37.8136, 144.9631);

        self::assertSame('3.6', substr((string) DB::scalar('SELECT postgis_version()'), 0, 3));
        self::assertSame(4326, (int) DB::scalar('SELECT ST_SRID(location_geography::geometry) FROM candidates WHERE id = ?', [$candidate->getKey()]));
        self::assertTrue(DB::table('pg_indexes')->where('indexname', 'candidates_location_geography_gist')->exists());

        foreach ([['latitude' => -37.8, 'longitude' => null], ['latitude' => 91, 'longitude' => 10]] as $coordinates) {
            DB::statement('SAVEPOINT invalid_coordinates');
            try {
                DB::table('candidates')->insert([
                    'organisation_id' => $organisation->getKey(), 'first_name' => 'Invalid', 'last_name' => 'Coordinates',
                    ...$coordinates, 'created_at' => now(), 'updated_at' => now(),
                ]);
                self::fail('Expected PostgreSQL to reject invalid coordinates.');
            } catch (QueryException $exception) {
                DB::statement('ROLLBACK TO SAVEPOINT invalid_coordinates');
                self::assertStringContainsString('candidates_', $exception->getMessage());
            }
        }
    }

    public function test_matching_query_count_is_fixed_as_candidate_count_grows(): void
    {
        $organisation = Organisation::query()->create(['name' => 'Bounded queries']);
        $job = $this->job($organisation, 'Nurse', -37.8136, 144.9631);
        $readModel = $this->app->make(CandidateMatchReadModel::class);

        $this->candidate($organisation, 'First', 'Nurse', -37.8137, 144.9632);
        self::assertSame(2, $this->matchingQueryCount($readModel, (int) $organisation->getKey(), (int) $job->getKey()));

        foreach (range(1, 25) as $index) {
            $this->candidate($organisation, "Candidate {$index}", 'Nurse', -37.81, 144.96);
        }

        self::assertSame(2, $this->matchingQueryCount($readModel, (int) $organisation->getKey(), (int) $job->getKey()));
    }

    /** @return array{Organisation, OrganisationMembership} */
    private function membership(User $user, string $role): array
    {
        $organisation = Organisation::query()->create(['name' => 'Matching test']);
        $membership = OrganisationMembership::query()->create([
            'organisation_id' => $organisation->getKey(), 'user_id' => $user->getKey(), 'role' => $role,
        ]);

        return [$organisation, $membership];
    }

    private function job(Organisation $organisation, ?string $occupation, ?float $latitude, ?float $longitude): Job
    {
        return Job::query()->create([
            'organisation_id' => $organisation->getKey(), 'title' => 'Synthetic role',
            'occupation' => $occupation, 'latitude' => $latitude, 'longitude' => $longitude, 'status' => JobStatus::Open,
        ]);
    }

    private function candidate(Organisation $organisation, string $firstName, ?string $occupation, ?float $latitude, ?float $longitude): Candidate
    {
        return Candidate::query()->create([
            'organisation_id' => $organisation->getKey(), 'first_name' => $firstName, 'last_name' => 'Candidate',
            'occupation' => $occupation, 'latitude' => $latitude, 'longitude' => $longitude,
        ]);
    }

    private function requirement(Organisation $organisation, Job $job): int
    {
        $definitionId = (int) DB::table('qualification_definitions')->insertGetId([
            'organisation_id' => $organisation->getKey(), 'name' => 'Registration', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('job_qualification_requirements')->insert([
            'organisation_id' => $organisation->getKey(), 'job_id' => $job->getKey(),
            'qualification_definition_id' => $definitionId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $definitionId;
    }

    private function credential(Organisation $organisation, Candidate $candidate, int $definitionId, ?string $expiresOn): void
    {
        DB::table('candidate_qualifications')->insert([
            'organisation_id' => $organisation->getKey(), 'candidate_id' => $candidate->getKey(),
            'qualification_definition_id' => $definitionId, 'expires_on' => $expiresOn,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function matchingQueryCount(CandidateMatchReadModel $readModel, int $organisationId, int $jobId): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $readModel->forJob(
            $organisationId,
            $jobId,
            new MatchCriteria(page: 1, perPage: 20, maxDistanceKm: null),
            new DateTimeImmutable('2026-09-28'),
            30,
        );
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
