<?php

namespace Tests\Feature\Recruitment;

use App\Modules\Candidate\Infrastructure\Persistence\Candidate;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use App\Modules\Recruitment\Application\Contracts\ApplicationCreator;
use App\Modules\Recruitment\Domain\ApplicationStatus;
use App\Modules\Recruitment\Infrastructure\Persistence\Job;
use App\Modules\Recruitment\Infrastructure\Persistence\RecruitmentApplication;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ApplicationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_users_cannot_access_application_routes(): void
    {
        $organisation = Organisation::query()->create(['name' => 'Protected']);
        $base = "/api/v1/organisations/{$organisation->getKey()}/applications";

        $this->getJson($base)->assertUnauthorized();
        $this->postJson($base, ['job_id' => 1, 'candidate_id' => 1])->assertUnauthorized();
        $this->getJson("{$base}/1")->assertUnauthorized();
    }

    #[DataProvider('writingRoles')]
    public function test_admin_and_recruiter_create_applied_application_with_initial_history(string $role): void
    {
        CarbonImmutable::setTestNow('2026-09-23 12:00:00 UTC');
        [$user, $organisation] = $this->actor($role);
        $job = $this->job($organisation, 'open');
        $candidate = $this->candidate($organisation, 'Ada', 'Lovelace');

        $response = $this->actingAs($user, 'web')->postJson(
            "/api/v1/organisations/{$organisation->getKey()}/applications",
            ['job_id' => $job->getKey(), 'candidate_id' => $candidate->getKey()],
        )->assertCreated()->assertJsonPath('data.status', 'applied')
            ->assertJsonPath('data.job.title', 'Nurse')
            ->assertJsonPath('data.candidate.first_name', 'Ada')
            ->assertJsonPath('data.applied_at', '2026-09-23T12:00:00+00:00')
            ->assertJsonMissingPath('data.organisation_id');

        $applicationId = $response->json('data.id');
        $this->assertDatabaseHas('applications', [
            'id' => $applicationId, 'organisation_id' => $organisation->getKey(),
            'job_id' => $job->getKey(), 'candidate_id' => $candidate->getKey(),
            'status' => 'applied', 'created_by_user_id' => $user->getKey(),
        ]);
        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $applicationId, 'organisation_id' => $organisation->getKey(),
            'from_status' => null, 'to_status' => 'applied', 'changed_by_user_id' => $user->getKey(),
        ]);
        $this->assertDatabaseCount('applications', 1);
        $this->assertDatabaseCount('application_status_history', 1);
        CarbonImmutable::setTestNow();
    }

    public function test_hiring_manager_is_forbidden_and_deactivated_membership_is_not_found(): void
    {
        [$manager, $organisation, $membership] = $this->actor('hiring_manager');
        $job = $this->job($organisation, 'open');
        $candidate = $this->candidate($organisation);
        $url = "/api/v1/organisations/{$organisation->getKey()}/applications";

        $this->actingAs($manager, 'web')->postJson($url, ['job_id' => $job->getKey(), 'candidate_id' => $candidate->getKey()])
            ->assertForbidden();
        $membership->update(['deactivated_at' => now()]);
        config()->set('app.debug', false);
        $this->actingAs($manager, 'web')->getJson($url)->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        $this->assertDatabaseCount('applications', 0);
    }

    #[DataProvider('nonOpenStatuses')]
    public function test_only_open_jobs_accept_applications(string $status): void
    {
        [$user, $organisation] = $this->actor('admin');
        $job = $this->job($organisation, $status);
        $candidate = $this->candidate($organisation);

        $this->actingAs($user, 'web')->postJson(
            "/api/v1/organisations/{$organisation->getKey()}/applications",
            ['job_id' => $job->getKey(), 'candidate_id' => $candidate->getKey()],
        )->assertConflict()->assertExactJson(['message' => 'Applications may only be created for an open job.']);
        $this->assertDatabaseCount('applications', 0);
        $this->assertDatabaseCount('application_status_history', 0);
    }

    public function test_missing_and_cross_tenant_targets_share_safe_not_found_semantics(): void
    {
        config()->set('app.debug', false);
        [$user, $organisation] = $this->actor('admin');
        [, $other] = $this->actor('admin');
        $job = $this->job($organisation, 'open');
        $candidate = $this->candidate($organisation);
        $foreignJob = $this->job($other, 'open');
        $foreignCandidate = $this->candidate($other);
        $url = "/api/v1/organisations/{$organisation->getKey()}/applications";

        foreach ([
            ['job_id' => 999999, 'candidate_id' => $candidate->getKey()],
            ['job_id' => $foreignJob->getKey(), 'candidate_id' => $candidate->getKey()],
            ['job_id' => $job->getKey(), 'candidate_id' => 999999],
            ['job_id' => $job->getKey(), 'candidate_id' => $foreignCandidate->getKey()],
        ] as $payload) {
            $this->actingAs($user, 'web')->postJson($url, $payload)
                ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        }
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_creation_rejects_client_controlled_fields_and_requires_ids(): void
    {
        [$user, $organisation] = $this->actor('admin');
        $this->actingAs($user, 'web')->postJson(
            "/api/v1/organisations/{$organisation->getKey()}/applications",
            [
                'organisation_id' => 9, 'status' => 'hired', 'applied_at' => now()->toAtomString(),
                'created_by_user_id' => 9, 'created_at' => now(), 'updated_at' => now(), 'id' => 9,
            ],
        )->assertUnprocessable()->assertJsonValidationErrors([
            'job_id', 'candidate_id', 'organisation_id', 'status', 'applied_at',
            'created_by_user_id', 'created_at', 'updated_at', 'id',
        ]);
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_duplicate_candidate_job_pair_returns_stable_conflict_and_one_history_stream(): void
    {
        [$user, $organisation] = $this->actor('admin');
        $job = $this->job($organisation, 'open');
        $candidate = $this->candidate($organisation);
        $url = "/api/v1/organisations/{$organisation->getKey()}/applications";
        $payload = ['job_id' => $job->getKey(), 'candidate_id' => $candidate->getKey()];

        $this->actingAs($user, 'web')->postJson($url, $payload)->assertCreated();
        $this->actingAs($user, 'web')->postJson($url, $payload)->assertConflict()
            ->assertExactJson(['message' => 'This candidate already has an application for this job.']);
        $this->assertDatabaseCount('applications', 1);
        $this->assertDatabaseCount('application_status_history', 1);
    }

    public function test_history_failure_rolls_back_application_creation(): void
    {
        [$user, $organisation] = $this->actor('admin');
        $job = $this->job($organisation, 'open');
        $candidate = $this->candidate($organisation);
        DB::unprepared("CREATE FUNCTION reject_application_history() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RAISE EXCEPTION ''forced history failure''; END'");
        DB::unprepared('CREATE TRIGGER reject_application_history BEFORE INSERT ON application_status_history FOR EACH ROW EXECUTE FUNCTION reject_application_history()');

        try {
            $this->app->make(ApplicationCreator::class)->create(
                (int) $organisation->getKey(), (int) $user->getKey(), (int) $job->getKey(), (int) $candidate->getKey(),
            );
            self::fail('Expected history persistence to fail.');
        } catch (QueryException) {
            $this->assertDatabaseCount('applications', 0);
            $this->assertDatabaseCount('application_status_history', 0);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS reject_application_history ON application_status_history');
            DB::unprepared('DROP FUNCTION IF EXISTS reject_application_history()');
        }
    }

    #[DataProvider('allRoles')]
    public function test_all_roles_list_and_view_tenant_applications_with_batched_candidate_lookup(string $role): void
    {
        [$user, $organisation] = $this->actor($role);
        $application = $this->application($organisation, $user);
        $base = "/api/v1/organisations/{$organisation->getKey()}/applications";

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user, 'web')->getJson($base)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $application->getKey());
        $listQueries = DB::getQueryLog();
        DB::flushQueryLog();
        $this->actingAs($user, 'web')->getJson("{$base}/{$application->getKey()}")->assertOk()
            ->assertJsonPath('data.candidate.first_name', 'Grace');
        $detailQueries = DB::getQueryLog();
        DB::disableQueryLog();

        self::assertCount(2, array_filter($listQueries, static fn (array $query): bool => str_contains($query['query'], 'from "applications"')));
        self::assertCount(1, array_filter($listQueries, static fn (array $query): bool => str_contains($query['query'], 'from "candidates"')));
        self::assertCount(1, array_filter($detailQueries, static fn (array $query): bool => str_contains($query['query'], 'from "applications"')));
        self::assertCount(1, array_filter($detailQueries, static fn (array $query): bool => str_contains($query['query'], 'from "candidates"')));
    }

    public function test_empty_application_list_returns_an_empty_page(): void
    {
        [$user, $organisation] = $this->actor('admin');

        $this->actingAs($user, 'web')
            ->getJson("/api/v1/organisations/{$organisation->getKey()}/applications")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 0);
    }

    public function test_list_filters_sorts_and_paginates_deterministically(): void
    {
        [$user, $organisation] = $this->actor('admin');
        $jobA = $this->job($organisation, 'open', 'Alpha Job');
        $jobB = $this->job($organisation, 'open', 'Beta Job');
        $candidateA = $this->candidate($organisation, 'Ada', 'One');
        $candidateB = $this->candidate($organisation, 'Ben', 'Two');
        $first = $this->application($organisation, $user, $jobA, $candidateA, 'applied', '2026-01-01');
        $second = $this->application($organisation, $user, $jobB, $candidateB, 'screening', '2026-02-01');
        $first->forceFill(['updated_at' => '2026-01-01'])->save();
        $second->forceFill(['updated_at' => '2026-03-01'])->save();
        $base = "/api/v1/organisations/{$organisation->getKey()}/applications";

        $this->actingAs($user, 'web')->getJson("{$base}?job_id={$jobA->getKey()}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first->getKey());
        $this->actingAs($user, 'web')->getJson("{$base}?candidate_id={$candidateB->getKey()}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second->getKey());
        $this->actingAs($user, 'web')->getJson("{$base}?status=screening")->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second->getKey());
        $this->actingAs($user, 'web')->getJson("{$base}?sort=applied_at&direction=asc&per_page=1")
            ->assertJsonPath('data.0.id', $first->getKey())->assertJsonPath('meta.last_page', 2);
        $this->actingAs($user, 'web')->getJson("{$base}?sort=applied_at&direction=desc")
            ->assertJsonPath('data.0.id', $second->getKey());
        $this->actingAs($user, 'web')->getJson("{$base}?sort=updated_at&direction=desc")
            ->assertJsonPath('data.0.id', $second->getKey());

        $tieCandidate = $this->candidate($organisation, 'Cara', 'Three');
        $tie = $this->application($organisation, $user, $jobA, $tieCandidate, 'applied', '2026-01-01');
        $this->actingAs($user, 'web')->getJson("{$base}?sort=applied_at&direction=asc")
            ->assertJsonPath('data.0.id', $first->getKey())
            ->assertJsonPath('data.1.id', $tie->getKey());
        $this->actingAs($user, 'web')->getJson("{$base}?sort=applied_at&direction=desc")
            ->assertJsonPath('data.1.id', $tie->getKey())
            ->assertJsonPath('data.2.id', $first->getKey());
        $this->actingAs($user, 'web')->getJson("{$base}?page=0&per_page=101&status=bad&sort=id&direction=sideways&role=admin")
            ->assertUnprocessable()->assertJsonValidationErrors(['page', 'per_page', 'status', 'sort', 'direction', 'query']);
    }

    public function test_missing_cross_tenant_and_deactivated_detail_share_404_semantics(): void
    {
        config()->set('app.debug', false);
        [$user, $organisation, $membership] = $this->actor('admin');
        [, $other] = $this->actor('admin');
        $otherUser = User::factory()->create();
        OrganisationMembership::query()->create(['organisation_id' => $other->getKey(), 'user_id' => $otherUser->getKey(), 'role' => 'admin']);
        $foreign = $this->application($other, $otherUser);
        $base = "/api/v1/organisations/{$organisation->getKey()}/applications";

        $cross = $this->actingAs($user, 'web')->getJson("{$base}/{$foreign->getKey()}")->assertNotFound();
        $missing = $this->actingAs($user, 'web')->getJson("{$base}/999999")->assertNotFound();
        self::assertSame($cross->getContent(), $missing->getContent());
        $membership->update(['deactivated_at' => now()]);
        $this->actingAs($user, 'web')->getJson($base)->assertNotFound();
    }

    public function test_stateful_creation_requires_csrf(): void
    {
        $this->app->instance('env', 'local');
        [$user, $organisation] = $this->actor('admin');
        $job = $this->job($organisation, 'open');
        $candidate = $this->candidate($organisation);
        $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost:3000')->postJson(
            "/api/v1/organisations/{$organisation->getKey()}/applications",
            ['job_id' => $job->getKey(), 'candidate_id' => $candidate->getKey()],
        )->assertStatus(419);
    }

    /** @return array{User, Organisation, OrganisationMembership} */
    private function actor(string $role): array
    {
        $user = User::factory()->create();
        $organisation = Organisation::query()->create(['name' => 'Test Organisation']);
        $membership = OrganisationMembership::query()->create([
            'organisation_id' => $organisation->getKey(), 'user_id' => $user->getKey(), 'role' => $role,
        ]);

        return [$user, $organisation, $membership];
    }

    private function job(Organisation $organisation, string $status, string $title = 'Nurse'): Job
    {
        return Job::query()->create(['organisation_id' => $organisation->getKey(), 'title' => $title, 'status' => $status]);
    }

    private function candidate(Organisation $organisation, string $first = 'Grace', string $last = 'Hopper'): Candidate
    {
        return Candidate::query()->create([
            'organisation_id' => $organisation->getKey(), 'first_name' => $first, 'last_name' => $last,
        ]);
    }

    private function application(
        Organisation $organisation,
        User $user,
        ?Job $job = null,
        ?Candidate $candidate = null,
        string $status = 'applied',
        string $appliedAt = '2026-01-01',
    ): RecruitmentApplication {
        $job ??= $this->job($organisation, 'open');
        $candidate ??= $this->candidate($organisation);

        return RecruitmentApplication::query()->create([
            'organisation_id' => $organisation->getKey(), 'job_id' => $job->getKey(),
            'candidate_id' => $candidate->getKey(), 'status' => ApplicationStatus::from($status),
            'applied_at' => $appliedAt, 'created_by_user_id' => $user->getKey(),
        ]);
    }

    /** @return array<string, array{string}> */
    public static function writingRoles(): array
    {
        return ['admin' => ['admin'], 'recruiter' => ['recruiter']];
    }

    /** @return array<string, array{string}> */
    public static function allRoles(): array
    {
        return [
            'admin' => ['admin'],
            'recruiter' => ['recruiter'],
            'hiring manager' => ['hiring_manager'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function nonOpenStatuses(): array
    {
        return ['draft' => ['draft'], 'closed' => ['closed'], 'archived' => ['archived']];
    }
}
