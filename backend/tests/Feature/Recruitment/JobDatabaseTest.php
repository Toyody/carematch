<?php

namespace Tests\Feature\Recruitment;

use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Recruitment\Infrastructure\Persistence\Job;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class JobDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_schema_has_the_required_constraints_and_index(): void
    {
        $constraints = DB::table('pg_constraint')->join('pg_class', 'pg_class.oid', '=', 'pg_constraint.conrelid')
            ->where('pg_class.relname', 'jobs')->pluck('pg_constraint.conname')->all();
        self::assertContains('jobs_pkey', $constraints);
        self::assertContains('jobs_organisation_id_foreign', $constraints);
        self::assertContains('jobs_organisation_id_id_unique', $constraints);
        self::assertContains('jobs_status_check', $constraints);
        self::assertContains('jobs_dates_check', $constraints);

        $indexes = DB::table('pg_indexes')->where('tablename', 'jobs')->pluck('indexname')->all();
        self::assertContains('jobs_organisation_id_status_created_at_index', $indexes);
        self::assertFalse(Schema::hasColumn('jobs', 'deleted_at'));
    }

    public function test_database_rejects_an_invalid_status(): void
    {
        $organisation = Organisation::query()->create(['name' => 'Status']);
        $this->expectException(QueryException::class);
        DB::table('jobs')->insert($this->row((int) $organisation->getKey(), ['status' => 'published']));
    }

    public function test_database_rejects_a_missing_organisation(): void
    {
        $this->expectException(QueryException::class);
        DB::table('jobs')->insert($this->row(999999, []));
    }

    public function test_database_rejects_a_missing_required_title(): void
    {
        $organisation = Organisation::query()->create(['name' => 'Required']);
        $this->expectException(QueryException::class);
        DB::table('jobs')->insert($this->row((int) $organisation->getKey(), ['title' => null]));
    }

    public function test_database_rejects_a_closing_date_before_the_opening_date(): void
    {
        $organisation = Organisation::query()->create(['name' => 'Dates']);
        $this->expectException(QueryException::class);
        DB::table('jobs')->insert($this->row((int) $organisation->getKey(), [
            'opened_at' => '2026-10-02 00:00:00+00',
            'closes_at' => '2026-10-01 00:00:00+00',
        ]));
    }

    public function test_organisation_deletion_is_restricted_when_jobs_exist(): void
    {
        $organisation = Organisation::query()->create(['name' => 'Protected']);
        Job::query()->create(['organisation_id' => $organisation->getKey(), 'title' => 'Nurse']);
        $this->expectException(QueryException::class);
        $organisation->delete();
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function row(int $organisationId, array $overrides): array
    {
        return [...[
            'organisation_id' => $organisationId,
            'title' => 'Job',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ], ...$overrides];
    }
}
