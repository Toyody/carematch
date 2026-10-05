<?php

namespace Tests\Integration\Infrastructure;

use App\Modules\Ai\Infrastructure\Jobs\ProcessCvExtractionJob;
use App\Modules\Candidate\Infrastructure\Persistence\Candidate;
use App\Modules\Candidate\Infrastructure\Persistence\CandidateDocument;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use Illuminate\Queue\SqsQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AlwaysFailingSqsJob;
use Tests\Support\RecordSqsSuccess;
use Tests\TestCase;

final class AsyncInfrastructureTest extends TestCase
{
    private static bool $schemaMigrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! filter_var(env('RUN_ASYNC_INTEGRATION', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('The disposable Redis/SQS integration stack is not enabled.');
        }

        if (! self::$schemaMigrated) {
            Artisan::call('migrate:fresh', ['--force' => true]);
            self::$schemaMigrated = true;
        }

        Cache::flush();
        $this->sqs()->clear('compliance');
        $this->sqs()->clear('carematch-dead-letter');
        $this->sqs()->clear('ai');
        $this->sqs()->clear('carematch-ai-dead-letter');
    }

    public function test_redis_cache_and_distributed_lock_are_available(): void
    {
        Cache::put('async-integration:cache', 'shared', 60);

        self::assertSame('shared', Cache::get('async-integration:cache'));

        $first = Cache::lock('async-integration:lock', 10);
        $second = Cache::lock('async-integration:lock', 10);

        self::assertTrue($first->get());
        self::assertFalse($second->get());
        $first->release();
        self::assertTrue($second->get());
        $second->release();
    }

    public function test_sqs_producer_worker_and_redis_backed_completion_path(): void
    {
        $marker = bin2hex(random_bytes(8));
        Queue::connection('sqs')->pushOn('compliance', new RecordSqsSuccess($marker));

        $this->runWorkerOnce();

        self::assertSame('processed', Cache::get('async-integration:'.$marker));
        self::assertSame(0, $this->sqs()->size('compliance'));
    }

    public function test_repeated_sqs_delivery_is_redriven_to_the_real_dead_letter_queue(): void
    {
        $queue = $this->sqs();
        $client = $queue->getSqs();
        $client->setQueueAttributes([
            'QueueUrl' => $queue->getQueue('compliance'),
            'Attributes' => ['VisibilityTimeout' => '1'],
        ]);

        $marker = bin2hex(random_bytes(8));
        Queue::connection('sqs')->pushOn('compliance', new AlwaysFailingSqsJob($marker));

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->runWorkerOnce();
            usleep(1_200_000);
        }

        $client->receiveMessage([
            'QueueUrl' => $queue->getQueue('compliance'),
            'WaitTimeSeconds' => 1,
        ]);

        $deadline = microtime(true) + 5;
        do {
            $messages = $client->receiveMessage([
                'QueueUrl' => $queue->getQueue('carematch-dead-letter'),
                'MaxNumberOfMessages' => 1,
                'WaitTimeSeconds' => 1,
                'AttributeNames' => ['ApproximateReceiveCount'],
            ])->get('Messages');
        } while ($messages === null && microtime(true) < $deadline);

        self::assertIsArray($messages);
        self::assertCount(1, $messages);
        self::assertStringContainsString($marker, (string) $messages[0]['Body']);
        self::assertGreaterThanOrEqual(3, (int) ($messages[0]['Attributes']['ApproximateReceiveCount'] ?? 0));
    }

    public function test_dedicated_ai_queue_worker_path_and_dead_letter_redrive(): void
    {
        $marker = bin2hex(random_bytes(8));
        Queue::connection('sqs')->pushOn('ai', new RecordSqsSuccess($marker));
        $this->runWorkerOnce('ai');
        self::assertSame('processed', Cache::get('async-integration:'.$marker));

        $queue = $this->sqs();
        $client = $queue->getSqs();
        $client->setQueueAttributes([
            'QueueUrl' => $queue->getQueue('ai'),
            'Attributes' => ['VisibilityTimeout' => '1'],
        ]);
        $failureMarker = bin2hex(random_bytes(8));
        Queue::connection('sqs')->pushOn('ai', new AlwaysFailingSqsJob($failureMarker));
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->runWorkerOnce('ai');
            usleep(1_200_000);
        }
        $client->receiveMessage(['QueueUrl' => $queue->getQueue('ai'), 'WaitTimeSeconds' => 1]);
        $deadline = microtime(true) + 5;
        do {
            $messages = $client->receiveMessage([
                'QueueUrl' => $queue->getQueue('carematch-ai-dead-letter'),
                'MaxNumberOfMessages' => 1,
                'WaitTimeSeconds' => 1,
                'AttributeNames' => ['ApproximateReceiveCount'],
            ])->get('Messages');
        } while ($messages === null && microtime(true) < $deadline);

        self::assertIsArray($messages);
        self::assertCount(1, $messages);
        self::assertStringContainsString($failureMarker, (string) $messages[0]['Body']);
        self::assertGreaterThanOrEqual(3, (int) ($messages[0]['Attributes']['ApproximateReceiveCount'] ?? 0));
    }

    public function test_ai_retry_exhaustion_persists_failure_and_still_redrives_to_real_dlq(): void
    {
        config()->set('carematch.ai.max_receive_count', 3);
        $user = User::factory()->create();
        $organisation = Organisation::query()->create(['name' => 'Synthetic async AI tenant']);
        OrganisationMembership::query()->create([
            'organisation_id' => $organisation->getKey(), 'user_id' => $user->getKey(), 'role' => 'admin',
        ]);
        $candidate = Candidate::query()->create([
            'organisation_id' => $organisation->getKey(), 'first_name' => 'Synthetic', 'last_name' => 'Candidate',
        ]);
        $storageKey = 'async-ai-'.bin2hex(random_bytes(8));
        Storage::disk('candidate_documents')->put($storageKey, "%PDF-1.4\nFAKE_AI_RETRYABLE\n%%EOF");
        $document = CandidateDocument::query()->create([
            'organisation_id' => $organisation->getKey(), 'candidate_id' => $candidate->getKey(),
            'original_name' => 'synthetic-retry.pdf', 'storage_key' => $storageKey,
            'mime_type' => 'application/pdf', 'size_bytes' => 37, 'uploaded_by_user_id' => $user->getKey(),
        ]);
        $now = now('UTC');
        $requestId = (int) DB::table('ai_cv_extractions')->insertGetId([
            'organisation_id' => $organisation->getKey(), 'candidate_id' => $candidate->getKey(),
            'candidate_document_id' => $document->getKey(), 'requested_by_user_id' => $user->getKey(),
            'idempotency_key_hash' => hash('sha256', $storageKey),
            'request_fingerprint' => hash('sha256', 'async-ai-request:'.$storageKey),
            'status' => 'queued', 'provider' => 'fake', 'model' => 'deterministic-fake-v1',
            'prompt_version' => 'cv_extraction_prompt_v1', 'schema_version' => 'cv_extraction_schema_v1',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        Queue::connection('sqs')->pushOn('ai', new ProcessCvExtractionJob($requestId));
        $this->runWorkerOnce('ai');
        $this->assertDatabaseHas('ai_cv_extractions', ['id' => $requestId, 'status' => 'processing', 'failure_code' => null]);
        usleep(5_200_000);
        $this->runWorkerOnce('ai');
        $this->assertDatabaseHas('ai_cv_extractions', ['id' => $requestId, 'status' => 'processing', 'failure_code' => null]);
        usleep(30_200_000);
        $this->runWorkerOnce('ai');
        $this->assertDatabaseHas('ai_cv_extractions', [
            'id' => $requestId, 'status' => 'failed', 'failure_code' => 'delivery_attempts_exhausted',
        ]);

        usleep(30_200_000);
        $queue = $this->sqs();
        $client = $queue->getSqs();
        $client->receiveMessage(['QueueUrl' => $queue->getQueue('ai'), 'WaitTimeSeconds' => 1]);
        $messages = $client->receiveMessage([
            'QueueUrl' => $queue->getQueue('carematch-ai-dead-letter'),
            'MaxNumberOfMessages' => 1,
            'WaitTimeSeconds' => 2,
            'AttributeNames' => ['ApproximateReceiveCount'],
        ])->get('Messages');

        self::assertIsArray($messages);
        self::assertCount(1, $messages);
        self::assertStringContainsString((string) $requestId, (string) $messages[0]['Body']);
        self::assertGreaterThanOrEqual(3, (int) ($messages[0]['Attributes']['ApproximateReceiveCount'] ?? 0));
    }

    private function runWorkerOnce(string $queue = 'compliance'): void
    {
        Artisan::call('queue:work', [
            'connection' => 'sqs',
            '--queue' => $queue,
            '--once' => true,
            '--tries' => 0,
            '--backoff' => 0,
            '--sleep' => 0,
            '--timeout' => 10,
        ]);
    }

    private function sqs(): SqsQueue
    {
        $queue = Queue::connection('sqs');
        self::assertInstanceOf(SqsQueue::class, $queue);

        return $queue;
    }
}
