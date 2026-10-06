<?php

namespace Tests\Integration\Infrastructure;

use App\Modules\Candidate\Infrastructure\Persistence\Candidate;
use App\Modules\Candidate\Infrastructure\Persistence\CandidateDocument;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CandidateDocumentS3IntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const string BUCKET = 'carematch-candidate-documents';

    private const string PREFIX = 'candidate-documents';

    protected function setUp(): void
    {
        parent::setUp();

        if (! filter_var(env('RUN_STORAGE_INTEGRATION', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('The disposable S3-compatible integration stack is not enabled.');
        }
    }

    public function test_private_s3_candidate_document_lifecycle_and_authorisation(): void
    {
        $this->requireScenario('available', 'recovered');
        $client = $this->s3();
        $this->createBucket($client);

        [$organisation, $admin, $candidate] = $this->context('admin', 'Storage tenant');
        $recruiter = User::factory()->create();
        OrganisationMembership::query()->create([
            'organisation_id' => $organisation->getKey(),
            'user_id' => $recruiter->getKey(),
            'role' => 'recruiter',
        ]);
        $hiringManager = User::factory()->create();
        OrganisationMembership::query()->create([
            'organisation_id' => $organisation->getKey(),
            'user_id' => $hiringManager->getKey(),
            'role' => 'hiring_manager',
        ]);
        $base = $this->baseUrl($organisation, $candidate);

        $adminResponse = $this->actingAs($admin, 'web')->post(
            $base,
            ['document' => $this->pdf('admin-synthetic.pdf')],
            ['Accept' => 'application/json'],
        )->assertCreated()
            ->assertJsonMissingPath('data.storage_key')
            ->assertJsonMissingPath('data.url');

        $documentId = (int) $adminResponse->json('data.id');
        $document = CandidateDocument::query()->findOrFail($documentId);
        $storageKey = (string) $document->getAttribute('storage_key');
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $storageKey);
        self::assertStringNotContainsString('admin-synthetic', $storageKey);
        self::assertTrue($client->doesObjectExistV2(self::BUCKET, self::PREFIX.'/'.$storageKey));

        $publicResponse = Http::timeout(5)->get(
            rtrim((string) env('AWS_CANDIDATE_DOCUMENTS_ENDPOINT'), '/')
            .'/'.self::BUCKET.'/'.self::PREFIX.'/'.$storageKey,
        );
        self::assertSame(403, $publicResponse->status());

        $download = $this->actingAs($admin, 'web')
            ->get($base."/{$documentId}/download", ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        self::assertSame($this->pdfContents(), $download->streamedContent());

        Auth::forgetGuards();
        $recruiterResponse = $this->actingAs($recruiter, 'web')->post(
            $base,
            ['document' => $this->pdf('recruiter-synthetic.pdf')],
            ['Accept' => 'application/json'],
        )->assertCreated();
        $recruiterDocumentId = (int) $recruiterResponse->json('data.id');

        Auth::forgetGuards();
        $this->actingAs($hiringManager, 'web')->post(
            $base,
            ['document' => $this->pdf('forbidden-synthetic.pdf')],
            ['Accept' => 'application/json'],
        )->assertForbidden();

        [$otherOrganisation, $otherUser, $otherCandidate] = $this->context('admin', 'Other storage tenant');
        Auth::forgetGuards();
        $this->actingAs($otherUser, 'web')
            ->getJson($this->baseUrl($otherOrganisation, $otherCandidate)."/{$documentId}/download")
            ->assertNotFound();

        Auth::forgetGuards();
        $this->actingAs($admin, 'web')
            ->deleteJson($base."/{$documentId}")
            ->assertNoContent();
        self::assertFalse($client->doesObjectExistV2(self::BUCKET, self::PREFIX.'/'.$storageKey));
        $this->actingAs($admin, 'web')
            ->getJson($base."/{$documentId}/download")
            ->assertNotFound();

        Auth::forgetGuards();
        $this->actingAs($recruiter, 'web')
            ->deleteJson($base."/{$recruiterDocumentId}")
            ->assertNoContent();
        $this->assertDatabaseCount('candidate_documents', 0);
    }

    public function test_unreachable_s3_returns_a_generic_error_without_committing_metadata(): void
    {
        $this->requireScenario('unavailable');
        config()->set('app.debug', false);
        [$organisation, $admin, $candidate] = $this->context('admin', 'Unavailable storage tenant');

        $this->actingAs($admin, 'web')->post(
            $this->baseUrl($organisation, $candidate),
            ['document' => $this->pdf('unavailable-synthetic.pdf')],
            ['Accept' => 'application/json'],
        )->assertInternalServerError()
            ->assertExactJson(['message' => 'The document could not be stored. Please try again.'])
            ->assertDontSee('127.0.0.1')
            ->assertDontSee('synthetic-storage-secret');

        $this->assertDatabaseCount('candidate_documents', 0);
    }

    private function s3(): S3Client
    {
        return new S3Client([
            'version' => 'latest',
            'region' => 'ap-southeast-2',
            'endpoint' => env('AWS_CANDIDATE_DOCUMENTS_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => 'synthetic-storage-access',
                'secret' => 'synthetic-storage-secret',
            ],
        ]);
    }

    private function createBucket(S3Client $client): void
    {
        try {
            $client->headBucket(['Bucket' => self::BUCKET]);
        } catch (AwsException) {
            $client->createBucket(['Bucket' => self::BUCKET]);
            $client->waitUntil('BucketExists', ['Bucket' => self::BUCKET]);
        }
    }

    /** @return array{Organisation, User, Candidate} */
    private function context(string $role, string $name): array
    {
        $user = User::factory()->create();
        $organisation = Organisation::query()->create(['name' => $name]);
        OrganisationMembership::query()->create([
            'organisation_id' => $organisation->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
        ]);
        $candidate = Candidate::query()->create([
            'organisation_id' => $organisation->getKey(),
            'first_name' => 'Synthetic',
            'last_name' => 'Storage Candidate',
        ]);

        return [$organisation, $user, $candidate];
    }

    private function baseUrl(Organisation $organisation, Candidate $candidate): string
    {
        return "/api/v1/organisations/{$organisation->getKey()}/candidates/{$candidate->getKey()}/documents";
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->pdfContents());
    }

    private function pdfContents(): string
    {
        return "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";
    }

    private function requireScenario(string ...$allowed): void
    {
        $scenario = env('STORAGE_INTEGRATION_SCENARIO');
        if (! is_string($scenario) || ! in_array($scenario, $allowed, true)) {
            $this->markTestSkipped('This assertion belongs to another storage integration phase.');
        }
    }
}
