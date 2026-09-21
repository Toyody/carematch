<?php

namespace Tests\Feature\Candidate;

use App\Modules\Candidate\Infrastructure\Persistence\Candidate;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CandidateDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_schema_has_required_constraints_and_indexes(): void
    {
        $constraints = DB::table('pg_constraint')
            ->join('pg_class', 'pg_class.oid', '=', 'pg_constraint.conrelid')
            ->where('pg_class.relname', 'candidates')
            ->pluck('pg_constraint.conname')
            ->all();

        self::assertContains('candidates_pkey', $constraints);
        self::assertContains('candidates_organisation_id_foreign', $constraints);
        self::assertContains('candidates_organisation_id_id_unique', $constraints);

        $indexes = DB::table('pg_indexes')
            ->where('tablename', 'candidates')
            ->pluck('indexname')
            ->all();

        self::assertContains('candidates_organisation_id_created_at_index', $indexes);
        self::assertContains('candidates_organisation_id_last_name_first_name_index', $indexes);
        self::assertNotContains('candidates_email_unique', $indexes);
        self::assertFalse(Schema::hasColumn('candidates', 'deleted_at'));
    }

    #[DataProvider('requiredColumnProvider')]
    public function test_candidate_names_and_organisation_are_not_nullable(string $column): void
    {
        $organisation = Organisation::query()->create(['name' => 'Required fields']);
        $values = [
            'organisation_id' => $organisation->getKey(),
            'first_name' => 'Required',
            'last_name' => 'Candidate',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $values[$column] = null;

        $this->expectException(QueryException::class);

        DB::table('candidates')->insert($values);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function requiredColumnProvider(): array
    {
        return [
            'organisation' => ['organisation_id'],
            'first name' => ['first_name'],
            'last name' => ['last_name'],
        ];
    }

    public function test_candidate_organisation_foreign_key_rejects_a_missing_tenant(): void
    {
        $this->expectException(QueryException::class);

        Candidate::query()->create([
            'organisation_id' => 999999,
            'first_name' => 'Missing',
            'last_name' => 'Tenant',
        ]);
    }

    public function test_organisation_deletion_is_restricted_when_candidates_exist(): void
    {
        $organisation = Organisation::query()->create(['name' => 'Protected']);
        Candidate::query()->create([
            'organisation_id' => $organisation->getKey(),
            'first_name' => 'Protected',
            'last_name' => 'Candidate',
        ]);

        $this->expectException(QueryException::class);

        $organisation->delete();
    }
}
