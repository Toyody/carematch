<?php

namespace Tests\Feature\Analytics;

use App\Modules\Candidate\Infrastructure\Persistence\Candidate;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use App\Modules\Recruitment\Infrastructure\Persistence\ApplicationStatusHistory;
use App\Modules\Recruitment\Infrastructure\Persistence\Job;
use App\Modules\Recruitment\Infrastructure\Persistence\RecruitmentApplication;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_unauthenticated_user_cannot_view_analytics(): void
    {
        $organisation = Organisation::query()->create(['name' => 'Protected']);

        $this->getJson($this->url($organisation))->assertUnauthorized();
    }

    #[DataProvider('roles')]
    public function test_every_active_role_can_view_zero_value_analytics_with_default_period(string $role): void
    {
        CarbonImmutable::setTestNow('2026-10-04 12:00:00 UTC');
        [$user, $organisation] = $this->actor($role);

        $response = $this->actingAs($user, 'web')->getJson($this->url($organisation))->assertOk();

        $response
            ->assertJsonPath('data.period.from', '2026-07-07')
            ->assertJsonPath('data.period.to', '2026-10-04')
            ->assertJsonPath('data.period.timezone', 'UTC')
            ->assertJsonPath('data.summary.new_candidates', 0)
            ->assertJsonPath('data.summary.new_jobs', 0)
            ->assertJsonPath('data.summary.applications', 0)
            ->assertJsonPath('data.funnel.applied', 0)
            ->assertJsonPath('data.current_statuses.rejected', 0)
            ->assertJsonPath('data.time_to_stage.interview.median_days', null)
            ->assertJsonPath('data.time_to_stage.interview.sample_size', 0)
            ->assertJsonPath('data.time_to_stage.hired.median_days', null)
            ->assertJsonCount(90, 'data.application_series')
            ->assertJsonPath('data.application_series.0.date', '2026-07-07')
            ->assertJsonPath('data.application_series.89.date', '2026-10-04')
            ->assertJsonPath('data.jobs', []);
    }

    public function test_explicit_period_reports_cohort_funnel_status_medians_series_and_jobs(): void
    {
        [$user, $organisation] = $this->actor('admin');
        [$foreignUser, $foreignOrganisation] = $this->actor('admin');

        $this->candidate($organisation, '2026-09-01 10:00:00+00');
        $this->candidate($organisation, '2026-09-05 10:00:00+00');
        $this->candidate($organisation, '2026-08-31 23:59:59+00');
        $this->candidate($foreignOrganisation, '2026-09-02 10:00:00+00');

        $jobOne = $this->job($organisation, 'Primary Job', '2026-09-01 09:00:00+00');
        $jobTwo = $this->job($organisation, 'Secondary Job', '2026-09-02 09:00:00+00');
        $foreignJob = $this->job($foreignOrganisation, 'Foreign Job', '2026-09-01 09:00:00+00');

        $applicationA = $this->application($organisation, $user, $jobOne, 'hired', '2026-09-01 00:00:00+00');
        $this->path($organisation, $user, $applicationA, [
            ['applied', '2026-09-01 00:00:00+00'],
            ['screening', '2026-09-02 00:00:00+00'],
            ['interview', '2026-09-04 00:00:00+00'],
            ['offer', '2026-09-08 00:00:00+00'],
            ['hired', '2026-09-11 00:00:00+00'],
        ]);
        $applicationB = $this->application($organisation, $user, $jobOne, 'rejected', '2026-09-02 00:00:00+00');
        $this->path($organisation, $user, $applicationB, [
            ['applied', '2026-09-02 00:00:00+00'],
            ['screening', '2026-09-03 00:00:00+00'],
            ['rejected', '2026-09-04 00:00:00+00'],
        ]);
        $applicationC = $this->application($organisation, $user, $jobOne, 'applied', '2026-09-03 00:00:00+00');
        $this->path($organisation, $user, $applicationC, [['applied', '2026-09-03 00:00:00+00']]);
        $applicationD = $this->application($organisation, $user, $jobTwo, 'interview', '2026-09-04 00:00:00+00');
        $this->path($organisation, $user, $applicationD, [
            ['applied', '2026-09-04 00:00:00+00'],
            ['screening', '2026-09-05 00:00:00+00'],
            ['interview', '2026-09-11 00:00:00+00'],
        ]);

        $foreignApplication = $this->application($foreignOrganisation, $foreignUser, $foreignJob, 'hired', '2026-09-01 00:00:00+00');
        $this->path($foreignOrganisation, $foreignUser, $foreignApplication, [
            ['applied', '2026-09-01 00:00:00+00'], ['hired', '2026-09-02 00:00:00+00'],
        ]);

        $response = $this->actingAs($user, 'web')->getJson($this->url($organisation).'?from=2026-09-01&to=2026-09-10')
            ->assertOk();

        $response
            ->assertJsonPath('data.summary', [
                'new_candidates' => 2, 'new_jobs' => 2, 'applications' => 4, 'hired' => 1, 'rejected' => 1,
            ])
            ->assertJsonPath('data.funnel', [
                'applied' => 4, 'screening' => 3, 'interview' => 2, 'offer' => 1, 'hired' => 1,
            ])
            ->assertJsonPath('data.current_statuses', [
                'applied' => 1, 'screening' => 0, 'interview' => 1, 'offer' => 0, 'hired' => 1, 'rejected' => 1,
            ])
            ->assertJsonPath('data.time_to_stage.interview.median_days', 5)
            ->assertJsonPath('data.time_to_stage.interview.sample_size', 2)
            ->assertJsonPath('data.time_to_stage.hired.median_days', 10)
            ->assertJsonPath('data.time_to_stage.hired.sample_size', 1)
            ->assertJsonCount(10, 'data.application_series')
            ->assertJsonPath('data.application_series.0.applications', 1)
            ->assertJsonPath('data.application_series.4.applications', 0)
            ->assertJsonPath('data.jobs.0.job_title', 'Primary Job')
            ->assertJsonPath('data.jobs.0.applications', 3)
            ->assertJsonPath('data.jobs.0.interview', 1)
            ->assertJsonPath('data.jobs.0.hired', 1)
            ->assertJsonPath('data.jobs.0.rejected', 1)
            ->assertJsonPath('data.jobs.1.job_title', 'Secondary Job')
            ->assertJsonMissing(['Foreign Job'])
            ->assertJsonMissingPath('data.organisation_id')
            ->assertJsonMissingPath('data.jobs.0.candidate');
    }

    public function test_period_is_inclusive_by_utc_calendar_date_and_excludes_outside_cohort(): void
    {
        [$user, $organisation] = $this->actor('admin');
        $job = $this->job($organisation, 'Boundary Job', '2026-09-01 00:00:00+00');

        foreach (['2026-08-31 23:59:59+00', '2026-09-01 00:00:00+00', '2026-09-01 23:59:59+00', '2026-09-02 00:00:00+00'] as $index => $appliedAt) {
            $application = $this->application($organisation, $user, $job, 'applied', $appliedAt);
            $this->path($organisation, $user, $application, [['applied', $appliedAt]]);
        }

        $this->actingAs($user, 'web')->getJson($this->url($organisation).'?from=2026-09-01&to=2026-09-01')
            ->assertOk()
            ->assertJsonPath('data.summary.applications', 2)
            ->assertJsonPath('data.application_series.0.applications', 2);
    }

    #[DataProvider('invalidPeriods')]
    public function test_invalid_or_incomplete_period_is_rejected(string $query): void
    {
        [$user, $organisation] = $this->actor('admin');

        $this->actingAs($user, 'web')->getJson($this->url($organisation).$query)->assertUnprocessable();
    }

    public function test_negative_stage_duration_is_excluded_instead_of_reported(): void
    {
        [$user, $organisation] = $this->actor('admin');
        $job = $this->job($organisation, 'Malformed History Job', '2026-09-01 00:00:00+00');
        $application = $this->application($organisation, $user, $job, 'interview', '2026-09-05 00:00:00+00');
        $this->path($organisation, $user, $application, [['interview', '2026-09-04 00:00:00+00']]);

        $this->actingAs($user, 'web')->getJson($this->url($organisation).'?from=2026-09-01&to=2026-09-10')
            ->assertOk()
            ->assertJsonPath('data.time_to_stage.interview.median_days', null)
            ->assertJsonPath('data.time_to_stage.interview.sample_size', 0);
    }

    public function test_query_count_is_fixed_independently_of_cohort_size(): void
    {
        [$user, $organisation] = $this->actor('admin');
        $job = $this->job($organisation, 'Bounded Query Job', '2026-09-01 00:00:00+00');
        for ($index = 0; $index < 12; $index++) {
            $application = $this->application($organisation, $user, $job, 'applied', '2026-09-02 00:00:00+00');
            $this->path($organisation, $user, $application, [['applied', '2026-09-02 00:00:00+00']]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user, 'web')->getJson($this->url($organisation).'?from=2026-09-01&to=2026-09-10')->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $analyticsQueries = array_filter(
            $queries,
            static fn (array $query): bool => str_contains(strtolower($query['query']), 'with cohort as')
                || str_contains(strtolower($query['query']), 'from generate_series'),
        );
        self::assertCount(4, $analyticsQueries);
    }

    public function test_job_ordering_is_stable_and_limited_to_ten(): void
    {
        [$user, $organisation] = $this->actor('admin');
        for ($index = 1; $index <= 11; $index++) {
            $job = $this->job($organisation, sprintf('Job %02d', $index), '2026-09-01 00:00:00+00');
            $application = $this->application($organisation, $user, $job, 'applied', '2026-09-02 00:00:00+00');
            $this->path($organisation, $user, $application, [['applied', '2026-09-02 00:00:00+00']]);
        }

        $response = $this->actingAs($user, 'web')->getJson($this->url($organisation).'?from=2026-09-01&to=2026-09-10')
            ->assertOk()
            ->assertJsonCount(10, 'data.jobs');

        self::assertSame('Job 01', $response->json('data.jobs.0.job_title'));
        self::assertSame('Job 10', $response->json('data.jobs.9.job_title'));
    }

    public function test_inaccessible_missing_and_deactivated_organisations_share_not_found_semantics(): void
    {
        config()->set('app.debug', false);
        [$user, $organisation, $membership] = $this->actor('admin');
        [, $foreignOrganisation] = $this->actor('admin');

        $missing = $this->actingAs($user, 'web')->getJson('/api/v1/organisations/999999/analytics')->assertNotFound();
        $foreign = $this->actingAs($user, 'web')->getJson($this->url($foreignOrganisation))->assertNotFound();
        self::assertSame($missing->getContent(), $foreign->getContent());

        $membership->update(['deactivated_at' => now()]);
        $inactive = $this->actingAs($user, 'web')->getJson($this->url($organisation))->assertNotFound();
        self::assertSame($missing->getContent(), $inactive->getContent());
    }

    /** @return array{User, Organisation, OrganisationMembership} */
    private function actor(string $role): array
    {
        $user = User::factory()->create();
        $organisation = Organisation::query()->create(['name' => 'Analytics '.uniqid()]);
        $membership = OrganisationMembership::query()->create([
            'organisation_id' => $organisation->getKey(), 'user_id' => $user->getKey(), 'role' => $role,
        ]);

        return [$user, $organisation, $membership];
    }

    private function candidate(Organisation $organisation, string $createdAt): Candidate
    {
        $candidate = new Candidate;
        $candidate->forceFill([
            'organisation_id' => $organisation->getKey(), 'first_name' => 'Synthetic', 'last_name' => uniqid(),
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ])->save();

        return $candidate;
    }

    private function job(Organisation $organisation, string $title, string $createdAt): Job
    {
        $job = new Job;
        $job->forceFill([
            'organisation_id' => $organisation->getKey(), 'title' => $title, 'status' => 'open',
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ])->save();

        return $job;
    }

    private function application(Organisation $organisation, User $user, Job $job, string $status, string $appliedAt): RecruitmentApplication
    {
        $candidate = $this->candidate($organisation, '2026-08-01 00:00:00+00');

        return RecruitmentApplication::query()->create([
            'organisation_id' => $organisation->getKey(), 'job_id' => $job->getKey(),
            'candidate_id' => $candidate->getKey(), 'status' => $status, 'applied_at' => $appliedAt,
            'created_by_user_id' => $user->getKey(),
        ]);
    }

    /** @param list<array{string, string}> $stages */
    private function path(Organisation $organisation, User $user, RecruitmentApplication $application, array $stages): void
    {
        $previous = null;
        foreach ($stages as [$stage, $createdAt]) {
            $history = new ApplicationStatusHistory;
            $history->forceFill([
                'organisation_id' => $organisation->getKey(), 'application_id' => $application->getKey(),
                'from_status' => $previous, 'to_status' => $stage,
                'changed_by_user_id' => $user->getKey(), 'created_at' => $createdAt,
            ])->save();
            $previous = $stage;
        }
    }

    private function url(Organisation $organisation): string
    {
        return "/api/v1/organisations/{$organisation->getKey()}/analytics";
    }

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return ['admin' => ['admin'], 'recruiter' => ['recruiter'], 'hiring manager' => ['hiring_manager']];
    }

    /** @return array<string, array{string}> */
    public static function invalidPeriods(): array
    {
        return [
            'missing to' => ['?from=2026-09-01'],
            'invalid date' => ['?from=2026-09-01&to=not-a-date'],
            'reverse range' => ['?from=2026-09-10&to=2026-09-01'],
            'more than 365 days' => ['?from=2025-09-01&to=2026-09-01'],
            'unsupported query' => ['?unexpected=value'],
        ];
    }
}
