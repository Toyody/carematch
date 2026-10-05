<?php

namespace Tests\Integration\Infrastructure;

use Illuminate\Queue\SqsQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\Support\AlwaysFailingSqsJob;
use Tests\Support\RecordSqsSuccess;
use Tests\TestCase;

final class AsyncInfrastructureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! filter_var(env('RUN_ASYNC_INTEGRATION', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('The disposable Redis/SQS integration stack is not enabled.');
        }

        Cache::flush();
        $this->sqs()->clear('compliance');
        $this->sqs()->clear('carematch-dead-letter');
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

    private function runWorkerOnce(): void
    {
        Artisan::call('queue:work', [
            'connection' => 'sqs',
            '--queue' => 'compliance',
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
