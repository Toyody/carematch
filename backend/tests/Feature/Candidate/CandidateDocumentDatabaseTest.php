<?php

namespace Tests\Feature\Candidate;

use App\Modules\Candidate\Infrastructure\Persistence\Candidate;
use App\Modules\Candidate\Infrastructure\Persistence\CandidateDocument;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CandidateDocumentDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_has_required_constraints_and_indexes(): void
    {
        $constraints = DB::table('pg_constraint')
            ->join('pg_class', 'pg_class.oid', '=', 'pg_constraint.conrelid')
            ->where('pg_class.relname', 'candidate_documents')
            ->pluck('pg_constraint.conname')
            ->all();

        self::assertContains('candidate_documents_pkey', $constraints);
        self::assertContains('candidate_documents_organisation_id_foreign', $constraints);
        self::assertContains('candidate_documents_organisation_id_id_unique', $constraints);
        self::assertContains('candidate_documents_organisation_candidate_foreign', $constraints);
        self::assertContains('candidate_documents_organisation_uploader_foreign', $constraints);
        self::assertContains('candidate_documents_storage_key_unique', $constraints);
        self::assertContains('candidate_documents_size_bytes_check', $constraints);

        $indexes = DB::table('pg_indexes')
            ->where('tablename', 'candidate_documents')
            ->pluck('indexdef', 'indexname')
            ->all();

        self::assertArrayHasKey('candidate_documents_org_candidate_created_index', $indexes);
        self::assertStringContainsString(
            '(organisation_id, candidate_id, created_at, id)',
            $indexes['candidate_documents_org_candidate_created_index'],
        );
    }

    public function test_composite_candidate_foreign_key_rejects_cross_tenant_metadata(): void
    {
        [$organisation, $membership, $candidate] = $this->context();
        $other = Organisation::query()->create(['name' => 'Other']);
        $otherCandidate = Candidate::query()->create([
            'organisation_id' => $other->getKey(),
            'first_name' => 'Other',
            'last_name' => 'Candidate',
        ]);

        $this->expectException(QueryException::class);

        $this->insertDocument(
            (int) $organisation->getKey(),
            (int) $otherCandidate->getKey(),
            (int) $membership->getAttribute('user_id'),
            'cross-tenant',
        );
    }

    public function test_composite_uploader_foreign_key_rejects_another_tenant_member(): void
    {
        [$organisation, , $candidate] = $this->context();
        $otherUser = User::factory()->create();
        $other = Organisation::query()->create(['name' => 'Other']);
        OrganisationMembership::query()->create([
            'organisation_id' => $other->getKey(),
            'user_id' => $otherUser->getKey(),
            'role' => 'admin',
        ]);

        $this->expectException(QueryException::class);

        $this->insertDocument(
            (int) $organisation->getKey(),
            (int) $candidate->getKey(),
            (int) $otherUser->getKey(),
            'wrong-uploader',
        );
    }

    #[DataProvider('invalidDocumentProvider')]
    public function test_positive_size_and_unique_storage_key_are_database_authorities(
        int $sizeBytes,
        string $storageKey,
        bool $insertFirst,
    ): void {
        [$organisation, $membership, $candidate] = $this->context();

        if ($insertFirst) {
            $this->insertDocument(
                (int) $organisation->getKey(),
                (int) $candidate->getKey(),
                (int) $membership->getAttribute('user_id'),
                $storageKey,
            );
        }

        $this->expectException(QueryException::class);

        DB::table('candidate_documents')->insert([
            'organisation_id' => $organisation->getKey(),
            'candidate_id' => $candidate->getKey(),
            'original_name' => 'resume.pdf',
            'storage_key' => $storageKey,
            'mime_type' => 'application/pdf',
            'size_bytes' => $sizeBytes,
            'uploaded_by_user_id' => $membership->getAttribute('user_id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, array{int, string, bool}> */
    public static function invalidDocumentProvider(): array
    {
        return [
            'zero bytes' => [0, 'zero-size', false],
            'duplicate storage key' => [100, 'duplicate-key', true],
        ];
    }

    #[DataProvider('restrictedParentProvider')]
    public function test_candidate_membership_and_organisation_deletion_are_restricted(string $parent): void
    {
        [$organisation, $membership, $candidate] = $this->context();
        $this->insertDocument(
            (int) $organisation->getKey(),
            (int) $candidate->getKey(),
            (int) $membership->getAttribute('user_id'),
            'restricted',
        );

        $model = match ($parent) {
            'candidate' => $candidate,
            'membership' => $membership,
            'organisation' => $organisation,
        };

        $this->expectException(QueryException::class);
        $model->delete();
    }

    /** @return array<string, array{string}> */
    public static function restrictedParentProvider(): array
    {
        return [
            'candidate' => ['candidate'],
            'uploader membership' => ['membership'],
            'organisation' => ['organisation'],
        ];
    }

    /** @return array{Organisation, OrganisationMembership, Candidate} */
    private function context(): array
    {
        $user = User::factory()->create();
        $organisation = Organisation::query()->create(['name' => 'Documents']);
        $membership = OrganisationMembership::query()->create([
            'organisation_id' => $organisation->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'admin',
        ]);
        $candidate = Candidate::query()->create([
            'organisation_id' => $organisation->getKey(),
            'first_name' => 'Test',
            'last_name' => 'Candidate',
        ]);

        return [$organisation, $membership, $candidate];
    }

    private function insertDocument(
        int $organisationId,
        int $candidateId,
        int $userId,
        string $storageKey,
    ): CandidateDocument {
        return CandidateDocument::query()->create([
            'organisation_id' => $organisationId,
            'candidate_id' => $candidateId,
            'original_name' => 'resume.pdf',
            'storage_key' => $storageKey,
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'uploaded_by_user_id' => $userId,
        ]);
    }
}
