<?php

namespace Tests\Feature\Dashboard;

use App\Modules\Candidate\Infrastructure\Persistence\Candidate;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use App\Modules\Recruitment\Infrastructure\Persistence\ApplicationStatusHistory;
use App\Modules\Recruitment\Infrastructure\Persistence\Job;
use App\Modules\Recruitment\Infrastructure\Persistence\RecruitmentApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_view_dashboard(): void
    {
        $organisation = Organisation::query()->create(['name' => 'Protected']);

        $this->getJson($this->url($organisation))->assertUnauthorized();
    }

    #[DataProvider('roles')]
    public function test_every_active_role_can_view_zero_value_dashboard(string $role): void
    {
        [$user, $organisation] = $this->actor($role);

        $this->actingAs($user, 'web')->getJson($this->url($organisation))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'candidate_count' => 0,
                    'open_job_count' => 0,
                    'application_counts' => [
                        'applied' => 0,
                        'screening' => 0,
                        'interview' => 0,
                        'offer' => 0,
                        'hired' => 0,
                        'rejected' => 0,
                    ],
                    'recent_application_activity' => [],
                ],
            ]);
    }

    public function test_metrics_and_recent_activity_are_tenant_scoped_and_bounded(): void
    {
        [$user, $organisation] = $this->actor('admin');
        [$otherUser, $otherOrganisation] = $this->actor('admin');

        $candidate = $this->candidate($organisation, 'Ada', 'Lovelace');
        $this->candidate($organisation, 'Grace', 'Hopper');
        $foreignCandidate = $this->candidate($otherOrganisation, 'Foreign', 'Person');

        $job = $this->job($organisation, 'open', 'Registered Nurse');
        $this->job($organisation, 'open', 'Care Worker');
        $this->job($organisation, 'draft', 'Draft Role');
        $this->job($organisation, 'closed', 'Closed Role');
        $this->job($organisation, 'archived', 'Archived Role');
        $foreignJob = $this->job($otherOrganisation, 'open', 'Foreign Role');

        $application = $this->application($organisation, $user, $job, $candidate, 'hired');
        foreach ([
            [null, 'applied'],
            ['applied', 'screening'],
            ['screening', 'interview'],
            ['interview', 'offer'],
            ['offer', 'hired'],
        ] as $index => [$from, $to]) {
            $this->history(
                $organisation,
                $user,
                $application,
                $from,
                $to,
                sprintf('2026-09-25 10:%02d:00+00', $index),
            );
        }

        $screeningApplication = $this->application(
            $organisation,
            $user,
            $job,
            $this->candidate($organisation, 'Mary', 'Seacole'),
            'screening',
        );
        $this->history($organisation, $user, $screeningApplication, 'applied', 'screening', '2026-09-25 09:00:00+00');

        $foreignApplication = $this->application(
            $otherOrganisation,
            $otherUser,
            $foreignJob,
            $foreignCandidate,
            'rejected',
        );
        $this->history($otherOrganisation, $otherUser, $foreignApplication, null, 'rejected', '2026-09-25 12:00:00+00');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($user, 'web')->getJson($this->url($organisation));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk()
            ->assertJsonPath('data.candidate_count', 3)
            ->assertJsonPath('data.open_job_count', 2)
            ->assertJsonPath('data.application_counts.applied', 0)
            ->assertJsonPath('data.application_counts.screening', 1)
            ->assertJsonPath('data.application_counts.interview', 0)
            ->assertJsonPath('data.application_counts.offer', 0)
            ->assertJsonPath('data.application_counts.hired', 1)
            ->assertJsonPath('data.application_counts.rejected', 0)
            ->assertJsonCount(5, 'data.recent_application_activity')
            ->assertJsonPath('data.recent_application_activity.0.to_status', 'hired')
            ->assertJsonPath('data.recent_application_activity.0.candidate.first_name', 'Ada')
            ->assertJsonPath('data.recent_application_activity.0.job.title', 'Registered Nurse')
            ->assertJsonPath('data.recent_application_activity.4.to_status', 'applied')
            ->assertJsonMissingPath('data.organisation_id')
            ->assertJsonMissingPath('data.recent_application_activity.0.note')
            ->assertJsonMissing(['Foreign', 'Foreign Role']);

        $dashboardQueries = array_filter(
            $queries,
            static fn (array $query): bool => str_contains($query['query'], 'from "candidates"')
                || str_contains($query['query'], 'from "jobs"')
                || str_contains($query['query'], 'from "applications"')
                || str_contains($query['query'], 'from "application_status_history"'),
        );
        self::assertCount(4, $dashboardQueries);
    }

    public function test_inaccessible_missing_and_deactivated_organisations_share_not_found_semantics(): void
    {
        config()->set('app.debug', false);
        [$user, $organisation, $membership] = $this->actor('admin');
        [, $otherOrganisation] = $this->actor('admin');

        $missing = $this->actingAs($user, 'web')->getJson('/api/v1/organisations/999999/dashboard')
            ->assertNotFound();
        $foreign = $this->actingAs($user, 'web')->getJson($this->url($otherOrganisation))
            ->assertNotFound();
        self::assertSame($missing->getContent(), $foreign->getContent());

        $membership->update(['deactivated_at' => now()]);
        $deactivated = $this->actingAs($user, 'web')->getJson($this->url($organisation))
            ->assertNotFound();
        self::assertSame($missing->getContent(), $deactivated->getContent());
    }

    public function test_recent_activity_index_exists_in_postgresql(): void
    {
        $indexes = DB::table('pg_indexes')
            ->where('tablename', 'application_status_history')
            ->pluck('indexname')
            ->all();

        self::assertContains('application_history_org_created_index', $indexes);
    }

    /** @return array{User, Organisation, OrganisationMembership} */
    private function actor(string $role): array
    {
        $user = User::factory()->create();
        $organisation = Organisation::query()->create(['name' => 'Test Organisation']);
        $membership = OrganisationMembership::query()->create([
            'organisation_id' => $organisation->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
        ]);

        return [$user, $organisation, $membership];
    }

    private function candidate(Organisation $organisation, string $firstName, string $lastName): Candidate
    {
        return Candidate::query()->create([
            'organisation_id' => $organisation->getKey(),
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
    }

    private function job(Organisation $organisation, string $status, string $title): Job
    {
        return Job::query()->create([
            'organisation_id' => $organisation->getKey(),
            'status' => $status,
            'title' => $title,
        ]);
    }

    private function application(
        Organisation $organisation,
        User $user,
        Job $job,
        Candidate $candidate,
        string $status,
    ): RecruitmentApplication {
        return RecruitmentApplication::query()->create([
            'organisation_id' => $organisation->getKey(),
            'job_id' => $job->getKey(),
            'candidate_id' => $candidate->getKey(),
            'status' => $status,
            'applied_at' => '2026-09-25 08:00:00+00',
            'created_by_user_id' => $user->getKey(),
        ]);
    }

    private function history(
        Organisation $organisation,
        User $user,
        RecruitmentApplication $application,
        ?string $fromStatus,
        string $toStatus,
        string $createdAt,
    ): void {
        $history = new ApplicationStatusHistory;
        $history->forceFill([
            'organisation_id' => $organisation->getKey(),
            'application_id' => $application->getKey(),
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by_user_id' => $user->getKey(),
            'created_at' => $createdAt,
        ])->save();
    }

    private function url(Organisation $organisation): string
    {
        return "/api/v1/organisations/{$organisation->getKey()}/dashboard";
    }

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return [
            'admin' => ['admin'],
            'recruiter' => ['recruiter'],
            'hiring manager' => ['hiring_manager'],
        ];
    }
}
