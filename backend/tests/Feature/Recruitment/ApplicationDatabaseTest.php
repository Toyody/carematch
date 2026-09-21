<?php

namespace Tests\Feature\Recruitment;

use App\Modules\Candidate\Infrastructure\Persistence\Candidate;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use App\Modules\Recruitment\Infrastructure\Persistence\Job;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ApplicationDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_has_required_composite_constraints_checks_and_indexes(): void
    {
        $applicationConstraints = $this->constraints('applications');
        foreach ([
            'applications_pkey', 'applications_organisation_id_foreign',
            'applications_organisation_id_id_unique', 'applications_org_job_candidate_unique',
            'applications_organisation_job_foreign', 'applications_organisation_candidate_foreign',
            'applications_organisation_creator_membership_foreign', 'applications_status_check',
        ] as $constraint) {
            self::assertContains($constraint, $applicationConstraints);
        }

        $historyConstraints = $this->constraints('application_status_history');
        foreach ([
            'application_status_history_pkey', 'application_history_organisation_application_foreign',
            'application_history_organisation_actor_membership_foreign',
            'application_history_from_status_check', 'application_history_to_status_check',
        ] as $constraint) {
            self::assertContains($constraint, $historyConstraints);
        }

        $indexes = DB::table('pg_indexes')->where('tablename', 'applications')->pluck('indexname')->all();
        self::assertContains('applications_organisation_id_candidate_id_index', $indexes);
        self::assertContains('applications_organisation_id_status_applied_at_id_index', $indexes);
        self::assertContains('applications_organisation_id_applied_at_id_index', $indexes);
        self::assertContains('applications_organisation_id_updated_at_id_index', $indexes);
    }

    public function test_composite_foreign_keys_reject_cross_tenant_job_candidate_and_creator(): void
    {
        [$organisation, $user, $job, $candidate] = $this->fixture('A');
        [$other, $otherUser, $otherJob, $otherCandidate] = $this->fixture('B');

        foreach ([
            ['job_id' => $otherJob->getKey()],
            ['candidate_id' => $otherCandidate->getKey()],
            ['created_by_user_id' => $otherUser->getKey()],
        ] as $override) {
            $this->assertDatabaseRejects(function () use ($organisation, $user, $job, $candidate, $override): void {
                DB::table('applications')->insert([...$this->row($organisation, $user, $job, $candidate), ...$override]);
            });
        }

        self::assertDatabaseCount('applications', 0);
        self::assertNotSame($organisation->getKey(), $other->getKey());
    }

    public function test_database_rejects_invalid_status_duplicate_pair_and_invalid_history_status(): void
    {
        [$organisation, $user, $job, $candidate] = $this->fixture('Checks');
        $row = $this->row($organisation, $user, $job, $candidate);

        $this->assertDatabaseRejects(function () use ($row): void {
            DB::table('applications')->insert([...$row, 'status' => 'unknown']);
        });
        self::assertDatabaseCount('applications', 0);

        $applicationId = DB::table('applications')->insertGetId($row);
        $this->assertDatabaseRejects(function () use ($row): void {
            DB::table('applications')->insert($row);
        });
        self::assertDatabaseCount('applications', 1);

        foreach ([['from_status' => 'unknown', 'to_status' => 'applied'], ['from_status' => null, 'to_status' => 'unknown']] as $statuses) {
            $this->assertDatabaseRejects(function () use ($statuses, $organisation, $applicationId, $user): void {
                DB::table('application_status_history')->insert([...$statuses,
                    'organisation_id' => $organisation->getKey(), 'application_id' => $applicationId,
                    'changed_by_user_id' => $user->getKey(), 'created_at' => now(),
                ]);
            });
        }
        self::assertDatabaseCount('application_status_history', 0);
    }

    /** @return list<string> */
    private function constraints(string $table): array
    {
        return DB::table('pg_constraint')->join('pg_class', 'pg_class.oid', '=', 'pg_constraint.conrelid')
            ->where('pg_class.relname', $table)->pluck('pg_constraint.conname')->all();
    }

    /** @return array{Organisation, User, Job, Candidate} */
    private function fixture(string $suffix): array
    {
        $user = User::factory()->create();
        $organisation = Organisation::query()->create(['name' => "Organisation {$suffix}"]);
        OrganisationMembership::query()->create([
            'organisation_id' => $organisation->getKey(), 'user_id' => $user->getKey(), 'role' => 'admin',
        ]);
        $job = Job::query()->create(['organisation_id' => $organisation->getKey(), 'title' => "Job {$suffix}", 'status' => 'open']);
        $candidate = Candidate::query()->create([
            'organisation_id' => $organisation->getKey(), 'first_name' => 'Case', 'last_name' => $suffix,
        ]);

        return [$organisation, $user, $job, $candidate];
    }

    /** @return array<string, mixed> */
    private function row(Organisation $organisation, User $user, Job $job, Candidate $candidate): array
    {
        return [
            'organisation_id' => $organisation->getKey(), 'job_id' => $job->getKey(),
            'candidate_id' => $candidate->getKey(), 'status' => 'applied', 'applied_at' => now(),
            'created_by_user_id' => $user->getKey(), 'created_at' => now(), 'updated_at' => now(),
        ];
    }

    /** @param callable(): void $operation */
    private function assertDatabaseRejects(callable $operation): void
    {
        DB::statement('SAVEPOINT application_constraint_test');
        try {
            $operation();
            self::fail('Expected PostgreSQL to reject the write.');
        } catch (QueryException) {
            DB::statement('ROLLBACK TO SAVEPOINT application_constraint_test');
        }
    }
}
