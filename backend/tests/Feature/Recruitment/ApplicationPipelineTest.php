<?php

namespace Tests\Feature\Recruitment;

use App\Modules\Candidate\Infrastructure\Persistence\Candidate;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use App\Modules\Recruitment\Application\Contracts\ApplicationTransitioner;
use App\Modules\Recruitment\Domain\ApplicationStatus;
use App\Modules\Recruitment\Infrastructure\Persistence\ApplicationStatusHistory;
use App\Modules\Recruitment\Infrastructure\Persistence\Job;
use App\Modules\Recruitment\Infrastructure\Persistence\RecruitmentApplication;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ApplicationPipelineTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('managerRolesAndValidTransitions')]
    public function test_admin_and_recruiter_can_perform_every_valid_transition(
        string $role,
        string $from,
        string $to,
    ): void {
        [$user, $organisation] = $this->actor($role);
        $application = $this->application($organisation, $user, $from);

        $this->actingAs($user, 'web')->postJson($this->transitionUrl($organisation, $application), [
            'to_status' => $to,
            'note' => '  Reviewed by the team.  ',
        ])->assertOk()->assertJsonPath('data.status', $to);

        $this->assertDatabaseHas('applications', ['id' => $application->getKey(), 'status' => $to]);
        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->getKey(), 'from_status' => $from,
            'to_status' => $to, 'changed_by_user_id' => $user->getKey(),
            'note' => 'Reviewed by the team.',
        ]);
        $this->assertDatabaseCount('application_status_history', 2);
    }

    #[DataProvider('hiringManagerTransitions')]
    public function test_hiring_manager_permissions_are_based_on_locked_current_and_target_status(
        string $from,
        string $to,
        int $expectedStatus,
    ): void {
        [$user, $organisation] = $this->actor('hiring_manager');
        $application = $this->application($organisation, $user, $from);

        $this->actingAs($user, 'web')
            ->postJson($this->transitionUrl($organisation, $application), ['to_status' => $to])
            ->assertStatus($expectedStatus);

        if ($expectedStatus === 200) {
            $this->assertDatabaseHas('applications', ['id' => $application->getKey(), 'status' => $to]);
            $this->assertDatabaseCount('application_status_history', 2);
        } else {
            $this->assertDatabaseHas('applications', ['id' => $application->getKey(), 'status' => $from]);
            $this->assertDatabaseCount('application_status_history', 1);
        }
    }

    #[DataProvider('invalidTransitions')]
    public function test_role_authorised_invalid_transitions_return_conflict_without_mutation(
        string $from,
        string $to,
    ): void {
        [$user, $organisation] = $this->actor('recruiter');
        $application = $this->application($organisation, $user, $from);

        $this->actingAs($user, 'web')
            ->postJson($this->transitionUrl($organisation, $application), ['to_status' => $to])
            ->assertConflict()
            ->assertExactJson(['message' => 'The requested application status transition is not allowed.']);

        $this->assertDatabaseHas('applications', ['id' => $application->getKey(), 'status' => $from]);
        $this->assertDatabaseCount('application_status_history', 1);
    }

    public function test_transition_validation_rejects_malformed_note_and_security_fields(): void
    {
        [$user, $organisation] = $this->actor('admin');
        $application = $this->application($organisation, $user);

        $this->actingAs($user, 'web')->postJson($this->transitionUrl($organisation, $application), [
            'to_status' => 'unknown', 'note' => str_repeat('x', 1001),
            'organisation_id' => 999, 'application_id' => 999, 'from_status' => 'offer',
            'changed_by_user_id' => 999, 'membership_id' => 999, 'user_id' => 999, 'role' => 'admin',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'to_status', 'note', 'organisation_id', 'application_id', 'changed_by_user_id',
            'membership_id', 'user_id', 'role',
        ]);
        $this->assertDatabaseCount('application_status_history', 1);
    }

    public function test_empty_note_is_normalised_to_null(): void
    {
        [$user, $organisation] = $this->actor('admin');
        $application = $this->application($organisation, $user);

        $this->actingAs($user, 'web')->postJson($this->transitionUrl($organisation, $application), [
            'to_status' => 'screening', 'note' => '   ',
        ])->assertOk();
        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->getKey(), 'to_status' => 'screening', 'note' => null,
        ]);
    }

    public function test_missing_cross_tenant_and_deactivated_membership_share_not_found_semantics(): void
    {
        config()->set('app.debug', false);
        [$user, $organisation, $membership] = $this->actor('admin');
        [$otherUser, $other] = $this->actor('admin');
        $foreign = $this->application($other, $otherUser);
        $base = "/api/v1/organisations/{$organisation->getKey()}/applications";

        $missing = $this->actingAs($user, 'web')->postJson("{$base}/999999/transitions", ['to_status' => 'screening'])->assertNotFound();
        $cross = $this->actingAs($user, 'web')->postJson("{$base}/{$foreign->getKey()}/transitions", ['to_status' => 'screening'])->assertNotFound();
        self::assertSame($missing->getContent(), $cross->getContent());

        $membership->update(['deactivated_at' => now()]);
        $this->actingAs($user, 'web')->postJson("{$base}/999999/transitions", ['to_status' => 'screening'])->assertNotFound();
    }

    public function test_transition_requires_authentication_and_csrf_for_stateful_spa_requests(): void
    {
        [$user, $organisation] = $this->actor('admin');
        $application = $this->application($organisation, $user);
        $url = $this->transitionUrl($organisation, $application);

        $this->postJson($url, ['to_status' => 'screening'])->assertUnauthorized();
        $this->app->instance('env', 'local');
        $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost:3000')
            ->postJson($url, ['to_status' => 'screening'])->assertStatus(419);
    }

    public function test_history_insert_failure_rolls_back_status_update(): void
    {
        [$user, $organisation] = $this->actor('admin');
        $application = $this->application($organisation, $user);
        DB::unprepared("CREATE FUNCTION reject_pipeline_history() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RAISE EXCEPTION ''forced history failure''; END'");
        DB::unprepared('CREATE TRIGGER reject_pipeline_history BEFORE INSERT ON application_status_history FOR EACH ROW EXECUTE FUNCTION reject_pipeline_history()');

        try {
            $this->app->make(ApplicationTransitioner::class)->transition(
                (int) $organisation->getKey(), (int) $application->getKey(), (int) $user->getKey(),
                OrganisationRole::Admin,
                ApplicationStatus::Screening, null,
            );
            self::fail('Expected history persistence to fail.');
        } catch (QueryException) {
            $this->assertDatabaseHas('applications', ['id' => $application->getKey(), 'status' => 'applied']);
            $this->assertDatabaseCount('application_status_history', 1);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS reject_pipeline_history ON application_status_history');
            DB::unprepared('DROP FUNCTION IF EXISTS reject_pipeline_history()');
        }
    }

    public function test_stale_sequential_transition_rechecks_current_locked_status(): void
    {
        [$user, $organisation] = $this->actor('admin');
        $application = $this->application($organisation, $user);
        $url = $this->transitionUrl($organisation, $application);

        $this->actingAs($user, 'web')->postJson($url, ['to_status' => 'screening'])->assertOk();
        $this->actingAs($user, 'web')->postJson($url, ['to_status' => 'screening'])->assertConflict();
        $this->assertDatabaseCount('application_status_history', 2);
    }

    public function test_full_pipeline_updates_timestamp_and_history_always_matches_current_status(): void
    {
        CarbonImmutable::setTestNow('2026-09-23 09:00:00 UTC');
        [$user, $organisation] = $this->actor('recruiter');
        $application = $this->application($organisation, $user);
        $originalUpdatedAt = $application->getAttribute('updated_at');
        $url = $this->transitionUrl($organisation, $application);

        foreach (['screening', 'interview', 'offer', 'hired'] as $offset => $target) {
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-23 10:00:00 UTC')->addMinutes($offset));
            $this->actingAs($user, 'web')->postJson($url, ['to_status' => $target])->assertOk();

            $application->refresh();
            self::assertSame($target, $application->getAttribute('status')->value);
            self::assertSame(
                $target,
                DB::table('application_status_history')
                    ->where('application_id', $application->getKey())
                    ->latest('id')
                    ->value('to_status'),
            );
        }

        self::assertNotEquals($originalUpdatedAt, $application->getAttribute('updated_at'));
        $this->assertDatabaseCount('application_status_history', 5);
        $this->actingAs($user, 'web')->postJson($url, ['to_status' => 'rejected'])->assertConflict();
        $this->assertDatabaseCount('application_status_history', 5);
        CarbonImmutable::setTestNow();
    }

    #[DataProvider('allRoles')]
    public function test_all_roles_can_read_immutable_tenant_scoped_history(string $role): void
    {
        CarbonImmutable::setTestNow('2026-09-23 12:00:00 UTC');
        [$user, $organisation] = $this->actor($role);
        $application = $this->application($organisation, $user);
        ApplicationStatusHistory::query()->create([
            'organisation_id' => $organisation->getKey(), 'application_id' => $application->getKey(),
            'from_status' => 'applied', 'to_status' => 'screening',
            'changed_by_user_id' => $user->getKey(), 'note' => 'Passed review.',
        ]);
        $url = $this->historyUrl($organisation, $application);

        $this->actingAs($user, 'web')->getJson($url)->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.from_status', null)
            ->assertJsonPath('data.0.to_status', 'applied')
            ->assertJsonPath('data.1.to_status', 'screening')
            ->assertJsonPath('data.1.changed_by_user_id', $user->getKey())
            ->assertJsonPath('data.1.note', 'Passed review.')
            ->assertJsonMissingPath('data.0.organisation_id');
        CarbonImmutable::setTestNow();
    }

    public function test_history_missing_cross_tenant_deactivated_and_unauthenticated_semantics(): void
    {
        config()->set('app.debug', false);
        [$user, $organisation, $membership] = $this->actor('admin');
        [$otherUser, $other] = $this->actor('admin');
        $foreign = $this->application($other, $otherUser);
        $base = "/api/v1/organisations/{$organisation->getKey()}/applications";

        $this->getJson("{$base}/999/history")->assertUnauthorized();
        $missing = $this->actingAs($user, 'web')->getJson("{$base}/999999/history")->assertNotFound();
        $cross = $this->actingAs($user, 'web')->getJson("{$base}/{$foreign->getKey()}/history")->assertNotFound();
        self::assertSame($missing->getContent(), $cross->getContent());
        $membership->update(['deactivated_at' => now()]);
        $this->actingAs($user, 'web')->getJson("{$base}/999999/history")->assertNotFound();
    }

    public function test_no_history_mutation_routes_are_exposed(): void
    {
        [$user, $organisation] = $this->actor('admin');
        $application = $this->application($organisation, $user);
        $url = $this->historyUrl($organisation, $application);

        $this->actingAs($user, 'web')->patchJson("{$url}/1", ['note' => 'changed'])->assertNotFound();
        $this->actingAs($user, 'web')->deleteJson("{$url}/1")->assertNotFound();
    }

    /** @return array{User, Organisation, OrganisationMembership} */
    private function actor(string $role): array
    {
        $user = User::factory()->create();
        $organisation = Organisation::query()->create(['name' => 'Pipeline Organisation']);
        $membership = OrganisationMembership::query()->create([
            'organisation_id' => $organisation->getKey(), 'user_id' => $user->getKey(), 'role' => $role,
        ]);

        return [$user, $organisation, $membership];
    }

    private function application(Organisation $organisation, User $user, string $status = 'applied'): RecruitmentApplication
    {
        $job = Job::query()->create(['organisation_id' => $organisation->getKey(), 'title' => 'Nurse', 'status' => 'open']);
        $candidate = Candidate::query()->create([
            'organisation_id' => $organisation->getKey(), 'first_name' => 'Ada', 'last_name' => 'Lovelace',
        ]);
        $application = RecruitmentApplication::query()->create([
            'organisation_id' => $organisation->getKey(), 'job_id' => $job->getKey(),
            'candidate_id' => $candidate->getKey(), 'status' => $status,
            'applied_at' => '2026-01-01', 'created_by_user_id' => $user->getKey(),
        ]);
        ApplicationStatusHistory::query()->create([
            'organisation_id' => $organisation->getKey(), 'application_id' => $application->getKey(),
            'from_status' => null, 'to_status' => $status, 'changed_by_user_id' => $user->getKey(),
        ]);

        return $application;
    }

    private function transitionUrl(Organisation $organisation, RecruitmentApplication $application): string
    {
        return "/api/v1/organisations/{$organisation->getKey()}/applications/{$application->getKey()}/transitions";
    }

    private function historyUrl(Organisation $organisation, RecruitmentApplication $application): string
    {
        return "/api/v1/organisations/{$organisation->getKey()}/applications/{$application->getKey()}/history";
    }

    /** @return array<string, array{string, string, string}> */
    public static function managerRolesAndValidTransitions(): array
    {
        $result = [];
        foreach (['admin', 'recruiter'] as $role) {
            foreach ([
                ['applied', 'screening'], ['applied', 'rejected'],
                ['screening', 'interview'], ['screening', 'rejected'],
                ['interview', 'offer'], ['interview', 'rejected'],
                ['offer', 'hired'], ['offer', 'rejected'],
            ] as [$from, $to]) {
                $result["{$role}:{$from}:{$to}"] = [$role, $from, $to];
            }
        }

        return $result;
    }

    /** @return array<string, array{string, string, int}> */
    public static function hiringManagerTransitions(): array
    {
        return [
            'interview to offer' => ['interview', 'offer', 200],
            'interview to rejected' => ['interview', 'rejected', 200],
            'applied to screening' => ['applied', 'screening', 403],
            'applied to rejected' => ['applied', 'rejected', 403],
            'screening to interview' => ['screening', 'interview', 403],
            'screening to rejected' => ['screening', 'rejected', 403],
            'offer to hired' => ['offer', 'hired', 403],
            'offer to rejected' => ['offer', 'rejected', 403],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function invalidTransitions(): array
    {
        return [
            'same status' => ['applied', 'applied'],
            'skip stage' => ['applied', 'offer'],
            'backwards' => ['offer', 'screening'],
            'hired terminal' => ['hired', 'rejected'],
            'rejected terminal' => ['rejected', 'screening'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function allRoles(): array
    {
        return ['admin' => ['admin'], 'recruiter' => ['recruiter'], 'hiring manager' => ['hiring_manager']];
    }
}
