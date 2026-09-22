<?php

namespace Tests\Feature\Candidate;

use App\Modules\Candidate\Application\Contracts\CandidateDocumentStorage;
use App\Modules\Candidate\Application\Contracts\CandidateDocumentStore;
use App\Modules\Candidate\Application\Data\CandidateDocumentRecord;
use App\Modules\Candidate\Application\Data\CandidateDocumentUpload;
use App\Modules\Candidate\Application\Exceptions\CandidateDocumentPersistenceFailure;
use App\Modules\Candidate\Application\Exceptions\CandidateDocumentStorageFailure;
use App\Modules\Candidate\Infrastructure\Persistence\Candidate;
use App\Modules\Candidate\Infrastructure\Persistence\CandidateDocument;
use App\Modules\Candidate\Infrastructure\Persistence\EloquentCandidateDocumentStore;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

final class CandidateDocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('carematch.candidate_documents.disk', 'candidate_documents');
        Storage::fake('candidate_documents');
    }

    public function test_document_routes_require_authentication(): void
    {
        [$organisation, $user, $candidate] = $this->context('admin');
        $document = $this->document(
            $organisation,
            $candidate,
            (int) $user->getKey(),
            'protected-key',
        );
        $base = $this->baseUrl($organisation, $candidate);

        $this->getJson($base)->assertUnauthorized();
        $this->post($base, ['document' => $this->pdf()], ['Accept' => 'application/json'])
            ->assertUnauthorized();
        $this->getJson("{$base}/{$document->getKey()}/download")->assertUnauthorized();
        $this->deleteJson("{$base}/{$document->getKey()}")->assertUnauthorized();
    }

    #[DataProvider('allRoles')]
    public function test_all_active_roles_list_and_download_private_documents(string $role): void
    {
        [$organisation, $user, $candidate] = $this->context($role);
        $older = $this->document($organisation, $candidate, (int) $user->getKey(), 'older-key', 'older.pdf');
        $newer = $this->document($organisation, $candidate, (int) $user->getKey(), 'newer-key', 'newer.pdf');
        Storage::disk('candidate_documents')->put('newer-key', $this->pdfContents());
        $base = $this->baseUrl($organisation, $candidate);

        $list = $this->actingAs($user, 'web')->getJson($base);

        $list->assertOk()
            ->assertJsonPath('data.0.id', $newer->getKey())
            ->assertJsonPath('data.1.id', $older->getKey())
            ->assertJsonMissingPath('data.0.storage_key')
            ->assertJsonMissingPath('data.0.organisation_id')
            ->assertJsonMissingPath('data.0.candidate_id');

        $download = $this->actingAs($user, 'web')
            ->get("{$base}/{$newer->getKey()}/download", ['Accept' => 'application/json']);

        $download->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=newer.pdf');
        self::assertSame($this->pdfContents(), $download->streamedContent());
        self::assertStringNotContainsString('newer-key', (string) $download->headers->get('content-disposition'));
    }

    #[DataProvider('writingRoles')]
    public function test_admin_and_recruiter_upload_server_inspected_pdf_and_delete_it(string $role): void
    {
        [$organisation, $user, $candidate] = $this->context($role);
        $base = $this->baseUrl($organisation, $candidate);

        $upload = $this->actingAs($user, 'web')->post(
            $base,
            ['document' => $this->pdf('resume.pdf')],
            ['Accept' => 'application/json'],
        );

        $upload->assertCreated()
            ->assertJsonPath('data.original_name', 'resume.pdf')
            ->assertJsonPath('data.mime_type', 'application/pdf')
            ->assertJsonPath('data.uploaded_by_user_id', $user->getKey())
            ->assertJsonMissingPath('data.storage_key');

        $document = CandidateDocument::query()->sole();
        $storageKey = (string) $document->getAttribute('storage_key');
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $storageKey);
        self::assertStringNotContainsString('resume.pdf', $storageKey);
        Storage::disk('candidate_documents')->assertExists($storageKey);

        $this->actingAs($user, 'web')
            ->deleteJson("{$base}/{$document->getKey()}")
            ->assertNoContent();

        Storage::disk('candidate_documents')->assertMissing($storageKey);
        $this->assertDatabaseMissing('candidate_documents', ['id' => $document->getKey()]);
    }

    public function test_a_real_docx_is_accepted_with_its_server_detected_mime_type(): void
    {
        [$organisation, $user, $candidate] = $this->context('admin');

        $response = $this->actingAs($user, 'web')->post(
            $this->baseUrl($organisation, $candidate),
            ['document' => $this->docx()],
            ['Accept' => 'application/json'],
        );

        $response->assertCreated()->assertJsonPath(
            'data.mime_type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );
    }

    public function test_separate_uploads_receive_distinct_high_entropy_internal_keys(): void
    {
        [$organisation, $user, $candidate] = $this->context('admin');
        $base = $this->baseUrl($organisation, $candidate);

        $this->actingAs($user, 'web')->post(
            $base,
            ['document' => $this->pdf('first.pdf')],
            ['Accept' => 'application/json'],
        )->assertCreated();
        $this->actingAs($user, 'web')->post(
            $base,
            ['document' => $this->pdf('second.pdf')],
            ['Accept' => 'application/json'],
        )->assertCreated();

        $keys = CandidateDocument::query()->orderBy('id')->pluck('storage_key')->all();
        self::assertCount(2, $keys);
        self::assertNotSame($keys[0], $keys[1]);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $keys[0]);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $keys[1]);
    }

    #[DataProvider('invalidFileProvider')]
    public function test_invalid_files_are_rejected_without_storage_or_metadata(string $case): void
    {
        [$organisation, $user, $candidate] = $this->context('admin');
        $file = match ($case) {
            'empty' => $this->uploadedFile('empty.pdf', ''),
            'oversized' => $this->uploadedFile(
                'large.pdf',
                "%PDF-1.4\n".str_repeat('a', (10 * 1024 * 1024) + 1),
            ),
            'text' => $this->uploadedFile('notes.txt', 'plain text'),
            'executable' => $this->uploadedFile('resume.exe', "MZ\x90\x00"),
            'image' => $this->uploadedFile(
                'photo.png',
                (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
            ),
            'misleading extension' => $this->uploadedFile('resume.pdf', 'not a pdf'),
        };

        $this->actingAs($user, 'web')->post(
            $this->baseUrl($organisation, $candidate),
            ['document' => $file],
            ['Accept' => 'application/json'],
        )->assertUnprocessable()->assertJsonValidationErrors('document');

        $this->assertDatabaseCount('candidate_documents', 0);
        self::assertSame([], Storage::disk('candidate_documents')->allFiles());
    }

    /** @return array<string, array{string}> */
    public static function invalidFileProvider(): array
    {
        return [
            'zero byte' => ['empty'],
            'over ten MiB' => ['oversized'],
            'text' => ['text'],
            'executable' => ['executable'],
            'image' => ['image'],
            'pdf name with text content' => ['misleading extension'],
        ];
    }

    public function test_path_like_filename_is_metadata_only_and_never_controls_storage_path(): void
    {
        [$organisation, $user, $candidate] = $this->context('admin');
        $file = new UploadedFile(
            $this->temporaryFile($this->pdfContents()),
            "../..\\unsafe\r\nname.pdf",
            null,
            UPLOAD_ERR_OK,
            true,
        );

        $response = $this->actingAs($user, 'web')->post(
            $this->baseUrl($organisation, $candidate),
            ['document' => $file],
            ['Accept' => 'application/json'],
        );

        $response->assertCreated();
        $name = $response->json('data.original_name');
        self::assertIsString($name);
        self::assertStringNotContainsString('/', $name);
        self::assertStringNotContainsString('\\', $name);
        self::assertStringNotContainsString("\r", $name);
        self::assertStringNotContainsString("\n", $name);
        $key = (string) CandidateDocument::query()->value('storage_key');
        self::assertSame([$key], Storage::disk('candidate_documents')->allFiles());
    }

    public function test_hiring_manager_is_read_only_for_documents(): void
    {
        config()->set('app.debug', false);
        [$organisation, $manager, $candidate] = $this->context('hiring_manager');
        $document = $this->document($organisation, $candidate, (int) $manager->getKey(), 'manager-key');
        Storage::disk('candidate_documents')->put('manager-key', $this->pdfContents());
        $base = $this->baseUrl($organisation, $candidate);

        $this->actingAs($manager, 'web')->post(
            $base,
            ['document' => $this->pdf()],
            ['Accept' => 'application/json'],
        )->assertForbidden();
        $this->actingAs($manager, 'web')
            ->deleteJson("{$base}/{$document->getKey()}")
            ->assertForbidden();

        $this->assertDatabaseHas('candidate_documents', ['id' => $document->getKey()]);
        Storage::disk('candidate_documents')->assertExists('manager-key');
    }

    public function test_nested_document_resolution_prevents_cross_tenant_and_cross_candidate_access(): void
    {
        config()->set('app.debug', false);
        [$organisationA, $userA, $candidateA] = $this->context('admin', 'A');
        $candidateA2 = Candidate::query()->create([
            'organisation_id' => $organisationA->getKey(),
            'first_name' => 'Second',
            'last_name' => 'Candidate',
        ]);
        [$organisationB, $userB, $candidateB] = $this->context('admin', 'B');
        $documentA = $this->document(
            $organisationA,
            $candidateA,
            (int) $userA->getKey(),
            'tenant-a-key',
        );
        $documentB = $this->document(
            $organisationB,
            $candidateB,
            (int) $userB->getKey(),
            'tenant-b-key',
        );
        Storage::disk('candidate_documents')->put('tenant-b-key', 'private tenant B bytes');

        $routes = [
            $this->baseUrl($organisationB, $candidateB),
            $this->baseUrl($organisationA, $candidateA)."/{$documentB->getKey()}/download",
            $this->baseUrl($organisationA, $candidateA)."/{$documentB->getKey()}",
            $this->baseUrl($organisationA, $candidateA2)."/{$documentA->getKey()}/download",
            $this->baseUrl($organisationA, $candidateA).'/999999/download',
        ];

        $this->actingAs($userA, 'web')->getJson($routes[0])->assertNotFound();
        $this->actingAs($userA, 'web')->getJson($routes[1])->assertNotFound();
        $this->actingAs($userA, 'web')->deleteJson($routes[2])->assertNotFound();
        $this->actingAs($userA, 'web')->getJson($routes[3])->assertNotFound();
        $this->actingAs($userA, 'web')->getJson($routes[4])->assertNotFound();

        Storage::disk('candidate_documents')->assertExists('tenant-b-key');
        self::assertSame('private tenant B bytes', Storage::disk('candidate_documents')->get('tenant-b-key'));
    }

    public function test_deactivated_and_missing_candidates_use_safe_not_found_semantics(): void
    {
        config()->set('app.debug', false);
        [$organisation, $user, $candidate, $membership] = $this->context('admin');
        $membership->update(['deactivated_at' => now()]);
        $base = $this->baseUrl($organisation, $candidate);

        $this->actingAs($user, 'web')->getJson($base)
            ->assertNotFound()->assertExactJson(['message' => 'Not Found']);

        $otherUser = User::factory()->create();
        [$otherOrganisation] = $this->contextForUser($otherUser, 'admin', 'Other');
        $this->actingAs($otherUser, 'web')
            ->getJson("/api/v1/organisations/{$otherOrganisation->getKey()}/candidates/999999/documents")
            ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
    }

    public function test_upload_and_delete_require_csrf_for_stateful_spa_requests(): void
    {
        $this->app->instance('env', 'local');
        [$organisation, $user, $candidate] = $this->context('admin');
        $document = $this->document($organisation, $candidate, (int) $user->getKey(), 'csrf-key');
        $base = $this->baseUrl($organisation, $candidate);

        $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost:3000')
            ->post($base, ['document' => $this->pdf()], ['Accept' => 'application/json'])
            ->assertStatus(419);
        $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost:3000')
            ->deleteJson("{$base}/{$document->getKey()}")
            ->assertStatus(419);
    }

    public function test_client_controlled_metadata_is_rejected(): void
    {
        [$organisation, $user, $candidate] = $this->context('admin');

        $this->actingAs($user, 'web')->post(
            $this->baseUrl($organisation, $candidate),
            [
                'document' => $this->pdf(),
                'organisation_id' => 999,
                'candidate_id' => 999,
                'storage_key' => 'client-key',
                'mime_type' => 'text/plain',
                'size_bytes' => 1,
                'uploaded_by_user_id' => 999,
            ],
            ['Accept' => 'application/json'],
        )->assertUnprocessable()->assertJsonValidationErrors([
            'organisation_id',
            'candidate_id',
            'storage_key',
            'mime_type',
            'size_bytes',
            'uploaded_by_user_id',
        ]);

        $this->assertDatabaseCount('candidate_documents', 0);
    }

    public function test_metadata_failure_compensates_by_removing_the_new_object(): void
    {
        config()->set('app.debug', false);
        [$organisation, $user, $candidate] = $this->context('admin');
        $this->app->bind(CandidateDocumentStore::class, static fn () => new class implements CandidateDocumentStore
        {
            public function list(int $organisationId, int $candidateId): array
            {
                return [];
            }

            public function create(int $organisationId, int $candidateId, int $uploadedByUserId, string $storageKey, CandidateDocumentUpload $upload): CandidateDocumentRecord
            {
                throw new RuntimeException('forced metadata failure');
            }

            public function find(int $organisationId, int $candidateId, int $documentId): ?CandidateDocumentRecord
            {
                return null;
            }

            public function delete(int $organisationId, int $candidateId, int $documentId): bool
            {
                return false;
            }
        });

        $this->actingAs($user, 'web')->post(
            $this->baseUrl($organisation, $candidate),
            ['document' => $this->pdf()],
            ['Accept' => 'application/json'],
        )->assertInternalServerError()
            ->assertExactJson(['message' => 'The document could not be stored. Please try again.']);

        self::assertSame([], Storage::disk('candidate_documents')->allFiles());
        $this->assertDatabaseCount('candidate_documents', 0);
    }

    public function test_storage_failure_never_inserts_metadata(): void
    {
        config()->set('app.debug', false);
        [$organisation, $user, $candidate] = $this->context('admin');
        $this->app->bind(CandidateDocumentStorage::class, static fn () => new class implements CandidateDocumentStorage
        {
            public function exists(string $storageKey): bool
            {
                return false;
            }

            public function put(string $sourcePath, string $storageKey): void
            {
                throw new CandidateDocumentStorageFailure;
            }

            public function get(string $storageKey): string
            {
                throw new CandidateDocumentStorageFailure;
            }

            public function delete(string $storageKey): void
            {
                throw new CandidateDocumentStorageFailure;
            }
        });

        $this->actingAs($user, 'web')->post(
            $this->baseUrl($organisation, $candidate),
            ['document' => $this->pdf()],
            ['Accept' => 'application/json'],
        )->assertInternalServerError();

        $this->assertDatabaseCount('candidate_documents', 0);
    }

    public function test_missing_stored_object_is_a_generic_internal_error_without_key_leakage(): void
    {
        config()->set('app.debug', false);
        [$organisation, $user, $candidate] = $this->context('admin');
        $document = $this->document($organisation, $candidate, (int) $user->getKey(), 'missing-secret-key');

        $this->actingAs($user, 'web')
            ->getJson($this->baseUrl($organisation, $candidate)."/{$document->getKey()}/download")
            ->assertInternalServerError()
            ->assertExactJson(['message' => 'The document is temporarily unavailable.'])
            ->assertDontSee('missing-secret-key');
    }

    public function test_storage_delete_failure_keeps_metadata_and_returns_a_generic_error(): void
    {
        config()->set('app.debug', false);
        [$organisation, $user, $candidate] = $this->context('admin');
        $document = $this->document($organisation, $candidate, (int) $user->getKey(), 'delete-failure-key');
        $this->app->bind(CandidateDocumentStorage::class, static fn () => new class implements CandidateDocumentStorage
        {
            public function exists(string $storageKey): bool
            {
                return true;
            }

            public function put(string $sourcePath, string $storageKey): void {}

            public function get(string $storageKey): string
            {
                return 'contents';
            }

            public function delete(string $storageKey): void
            {
                throw new CandidateDocumentStorageFailure;
            }
        });

        $this->actingAs($user, 'web')
            ->deleteJson($this->baseUrl($organisation, $candidate)."/{$document->getKey()}")
            ->assertInternalServerError()
            ->assertDontSee('delete-failure-key');

        $this->assertDatabaseHas('candidate_documents', ['id' => $document->getKey()]);
    }

    public function test_metadata_delete_failure_occurs_only_after_the_private_object_is_removed(): void
    {
        config()->set('app.debug', false);
        [$organisation, $user, $candidate] = $this->context('admin');
        $document = $this->document($organisation, $candidate, (int) $user->getKey(), 'db-delete-key');
        Storage::disk('candidate_documents')->put('db-delete-key', $this->pdfContents());
        $realStore = $this->app->make(EloquentCandidateDocumentStore::class);
        $this->app->bind(CandidateDocumentStore::class, static fn () => new class($realStore) implements CandidateDocumentStore
        {
            public function __construct(private readonly EloquentCandidateDocumentStore $store) {}

            public function list(int $organisationId, int $candidateId): array
            {
                return $this->store->list($organisationId, $candidateId);
            }

            public function create(int $organisationId, int $candidateId, int $uploadedByUserId, string $storageKey, CandidateDocumentUpload $upload): CandidateDocumentRecord
            {
                return $this->store->create($organisationId, $candidateId, $uploadedByUserId, $storageKey, $upload);
            }

            public function find(int $organisationId, int $candidateId, int $documentId): ?CandidateDocumentRecord
            {
                return $this->store->find($organisationId, $candidateId, $documentId);
            }

            public function delete(int $organisationId, int $candidateId, int $documentId): bool
            {
                throw new CandidateDocumentPersistenceFailure;
            }
        });

        $this->actingAs($user, 'web')
            ->deleteJson($this->baseUrl($organisation, $candidate)."/{$document->getKey()}")
            ->assertInternalServerError()
            ->assertExactJson(['message' => 'The document could not be deleted. Please try again.']);

        Storage::disk('candidate_documents')->assertMissing('db-delete-key');
        $this->assertDatabaseHas('candidate_documents', ['id' => $document->getKey()]);
    }

    /** @return array{Organisation, User, Candidate, OrganisationMembership} */
    private function context(string $role, string $name = 'Documents'): array
    {
        $user = User::factory()->create();

        return $this->contextForUser($user, $role, $name);
    }

    /** @return array{Organisation, User, Candidate, OrganisationMembership} */
    private function contextForUser(User $user, string $role, string $name): array
    {
        $organisation = Organisation::query()->create(['name' => $name]);
        $membership = OrganisationMembership::query()->create([
            'organisation_id' => $organisation->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
        ]);
        $candidate = Candidate::query()->create([
            'organisation_id' => $organisation->getKey(),
            'first_name' => 'Document',
            'last_name' => 'Candidate',
        ]);

        return [$organisation, $user, $candidate, $membership];
    }

    private function document(
        Organisation $organisation,
        Candidate $candidate,
        int $userId,
        string $storageKey,
        string $name = 'resume.pdf',
    ): CandidateDocument {
        return CandidateDocument::query()->create([
            'organisation_id' => $organisation->getKey(),
            'candidate_id' => $candidate->getKey(),
            'original_name' => $name,
            'storage_key' => $storageKey,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($this->pdfContents()),
            'uploaded_by_user_id' => $userId,
        ]);
    }

    private function baseUrl(Organisation $organisation, Candidate $candidate): string
    {
        return "/api/v1/organisations/{$organisation->getKey()}/candidates/{$candidate->getKey()}/documents";
    }

    private function pdf(string $name = 'resume.pdf'): UploadedFile
    {
        return $this->uploadedFile($name, $this->pdfContents());
    }

    private function pdfContents(): string
    {
        return "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";
    }

    private function docx(): UploadedFile
    {
        $path = $this->temporaryFile('');
        $zip = new ZipArchive;
        self::assertTrue($zip->open($path, ZipArchive::OVERWRITE));
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Synthetic CV</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        return new UploadedFile($path, 'resume.docx', null, UPLOAD_ERR_OK, true);
    }

    private function temporaryFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'carematch-document-');
        self::assertIsString($path);
        file_put_contents($path, $contents);
        $this->beforeApplicationDestroyed(static function () use ($path): void {
            if (is_file($path)) {
                unlink($path);
            }
        });

        return $path;
    }

    private function uploadedFile(string $name, string $contents): UploadedFile
    {
        return new UploadedFile(
            $this->temporaryFile($contents),
            $name,
            null,
            UPLOAD_ERR_OK,
            true,
        );
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
    public static function writingRoles(): array
    {
        return [
            'admin' => ['admin'],
            'recruiter' => ['recruiter'],
        ];
    }
}
