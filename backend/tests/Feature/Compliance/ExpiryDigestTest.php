<?php

namespace Tests\Feature\Compliance;

use App\Modules\Candidate\Infrastructure\Persistence\Candidate;
use App\Modules\Candidate\Infrastructure\Persistence\CandidateQualification;
use App\Modules\Compliance\Application\Actions\RequestExpiryDigest;
use App\Modules\Compliance\Application\Contracts\ExpiryDigestDispatcher;
use App\Modules\Compliance\Application\Contracts\ExpiryDigestNotifier;
use App\Modules\Compliance\Application\Contracts\ExpiryDigestRequestStore;
use App\Modules\Compliance\Application\Contracts\ExpiryDigestSummaryReadModel;
use App\Modules\Compliance\Application\Exceptions\ExpiryDigestDeliveryException;
use App\Modules\Compliance\Application\Exceptions\ExpiryDigestIdempotencyConflict;
use App\Modules\Compliance\Infrastructure\Jobs\SendExpiryDigestJob;
use App\Modules\Compliance\Infrastructure\Notifications\ComplianceExpiryDigestNotification;
use App\Modules\Compliance\Infrastructure\Persistence\ExpiryDigestRequest;
use App\Modules\Compliance\Infrastructure\Persistence\QualificationDefinition;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PDO;
use ReflectionMethod;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class ExpiryDigestTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_admin_retry_reuses_one_durable_operation_and_redispatches_while_queued(): void
    {
        Queue::fake();
        [$admin, $organisation] = $this->actor('admin');
        $url = $this->collectionUrl($organisation);

        $first = $this->actingAs($admin, 'web')->postJson($url, [], ['Idempotency-Key' => 'digest-2026-10'])
            ->assertAccepted()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonMissingPath('data.organisation_id')
            ->assertJsonMissingPath('data.requested_by_user_id');
        $second = $this->actingAs($admin, 'web')->postJson($url, [], ['Idempotency-Key' => 'digest-2026-10'])
            ->assertAccepted();

        self::assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('compliance_expiry_digest_requests', 1);
        $this->assertDatabaseCount('audit_events', 1);
        Queue::assertPushed(SendExpiryDigestJob::class, 2);
    }

    public function test_same_key_retry_recovers_after_dispatch_failure(): void
    {
        [$admin, $organisation, $membership] = $this->actor('admin');
        $tenant = new TenantContext(
            (int) $organisation->getKey(),
            (int) $admin->getKey(),
            (int) $membership->getKey(),
            OrganisationRole::Admin,
        );
        $dispatcher = new class implements ExpiryDigestDispatcher
        {
            public int $attempts = 0;

            public function dispatchAfterCommit(int $requestId): void
            {
                $this->attempts++;

                if ($this->attempts === 1) {
                    throw new \RuntimeException('Controlled dispatch failure.');
                }
            }
        };
        $this->app->instance(ExpiryDigestDispatcher::class, $dispatcher);
        $action = $this->app->make(RequestExpiryDigest::class);

        try {
            $action->handle($tenant, 'dispatch-recovery');
            self::fail('The controlled dispatch failure was not propagated.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Controlled dispatch failure.', $exception->getMessage());
        }

        $requestId = (int) ExpiryDigestRequest::query()->sole()->getKey();
        $retried = $action->handle($tenant, 'dispatch-recovery');

        self::assertSame($requestId, $retried->id);
        self::assertSame(2, $dispatcher->attempts);
        $this->assertDatabaseCount('compliance_expiry_digest_requests', 1);
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_idempotency_scope_includes_user_and_organisation(): void
    {
        Queue::fake();
        [$firstAdmin, $firstOrganisation] = $this->actor('admin');
        [$secondAdmin, $secondOrganisation] = $this->actor('admin');
        $sameOrganisationAdmin = User::factory()->create();
        OrganisationMembership::query()->create([
            'organisation_id' => $firstOrganisation->getKey(),
            'user_id' => $sameOrganisationAdmin->getKey(),
            'role' => 'admin',
        ]);

        foreach ([
            [$firstAdmin, $firstOrganisation],
            [$sameOrganisationAdmin, $firstOrganisation],
            [$secondAdmin, $secondOrganisation],
        ] as [$user, $organisation]) {
            Auth::forgetGuards();
            $this->actingAs($user, 'web')->postJson(
                $this->collectionUrl($organisation),
                [],
                ['Idempotency-Key' => 'same-client-key'],
            )->assertAccepted();
        }

        $this->assertDatabaseCount('compliance_expiry_digest_requests', 3);
        Queue::assertPushed(SendExpiryDigestJob::class, 3);
    }

    public function test_conflicting_fingerprint_is_rejected_by_the_durable_store(): void
    {
        [$admin, $organisation, $membership] = $this->actor('admin');
        $tenant = new TenantContext(
            (int) $organisation->getKey(),
            (int) $admin->getKey(),
            (int) $membership->getKey(),
            OrganisationRole::Admin,
        );
        $store = $this->app->make(ExpiryDigestRequestStore::class);

        $store->createOrRetrieve($tenant, hash('sha256', 'same-key'), hash('sha256', 'input-a'));

        $this->expectException(ExpiryDigestIdempotencyConflict::class);
        $store->createOrRetrieve($tenant, hash('sha256', 'same-key'), hash('sha256', 'input-b'));
    }

    public function test_concurrent_duplicate_database_writes_create_one_durable_request(): void
    {
        $setup = $this->databasePdo();
        $email = 'concurrent-'.bin2hex(random_bytes(6)).'@example.test';
        $userStatement = $setup->prepare("INSERT INTO users (name, email, password, created_at, updated_at) VALUES ('Concurrency Test', ?, 'not-used', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING id");
        $userStatement->execute([$email]);
        $userId = (int) $userStatement->fetchColumn();
        $organisationId = (int) $setup->query("INSERT INTO organisations (name, created_at, updated_at) VALUES ('Concurrency Test', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING id")->fetchColumn();
        $values = [
            $organisationId,
            $userId,
            hash('sha256', 'concurrent-key'),
            hash('sha256', 'compliance.expiry-digest:v1:{}'),
        ];
        $connection = config('database.connections.pgsql');
        self::assertIsArray($connection);
        $environment = [
            'TEST_DB_HOST' => (string) $connection['host'],
            'TEST_DB_PORT' => (string) $connection['port'],
            'TEST_DB_DATABASE' => (string) $connection['database'],
            'TEST_DB_USERNAME' => (string) $connection['username'],
            'TEST_DB_PASSWORD' => (string) $connection['password'],
            'TEST_DIGEST_VALUES' => json_encode($values, JSON_THROW_ON_ERROR),
            'TEST_START_AT' => (string) (microtime(true) + 0.5),
        ];
        $command = [PHP_BINARY, base_path('tests/Support/concurrent-digest-insert.php')];
        $first = new Process($command, base_path(), $environment);
        $second = new Process($command, base_path(), $environment);
        $first->start();
        $second->start();
        $first->wait();
        $second->wait();

        self::assertTrue($first->isSuccessful(), $first->getErrorOutput());
        self::assertTrue($second->isSuccessful(), $second->getErrorOutput());
        self::assertSame(1, (int) $setup->query("SELECT count(*) FROM compliance_expiry_digest_requests WHERE organisation_id = {$organisationId}")->fetchColumn());
        $setup->exec("DELETE FROM compliance_expiry_digest_requests WHERE organisation_id = {$organisationId}");
        $setup->exec("DELETE FROM organisations WHERE id = {$organisationId}");
        $setup->exec("DELETE FROM users WHERE id = {$userId}");
    }

    public function test_invalid_key_or_request_body_is_rejected(): void
    {
        [$admin, $organisation] = $this->actor('admin');
        $url = $this->collectionUrl($organisation);

        $this->actingAs($admin, 'web')->postJson($url)->assertUnprocessable();
        $this->actingAs($admin, 'web')->postJson($url, [], ['Idempotency-Key' => str_repeat('x', 129)])
            ->assertUnprocessable();
        $this->actingAs($admin, 'web')->postJson($url, ['organisation_id' => $organisation->getKey()], ['Idempotency-Key' => 'body-switch'])
            ->assertUnprocessable();
    }

    public function test_only_admin_can_request_or_view_and_tenant_isolation_is_common_not_found(): void
    {
        Queue::fake();
        [$admin, $organisation] = $this->actor('admin');
        [$foreignAdmin, $foreignOrganisation] = $this->actor('admin');
        [$recruiter] = $this->membershipActor($organisation, 'recruiter');
        [$hiringManager] = $this->membershipActor($organisation, 'hiring_manager');

        $request = $this->actingAs($admin, 'web')->postJson(
            $this->collectionUrl($organisation),
            [],
            ['Idempotency-Key' => 'authorised'],
        )->assertAccepted();
        $requestId = (int) $request->json('data.id');

        Auth::forgetGuards();
        $this->actingAs($recruiter, 'web')->postJson($this->collectionUrl($organisation), [], ['Idempotency-Key' => 'recruiter'])
            ->assertForbidden();
        Auth::forgetGuards();
        $this->actingAs($hiringManager, 'web')->postJson($this->collectionUrl($organisation), [], ['Idempotency-Key' => 'manager'])
            ->assertForbidden();
        Auth::forgetGuards();
        $this->actingAs($foreignAdmin, 'web')->getJson($this->statusUrl($foreignOrganisation, $requestId))
            ->assertNotFound();
        Auth::forgetGuards();
        $this->actingAs($admin, 'web')->getJson($this->statusUrl($organisation, $requestId))
            ->assertOk()->assertJsonPath('data.id', $requestId);
    }

    public function test_public_demo_blocks_digest_delivery_server_side(): void
    {
        Queue::fake();
        config()->set('carematch.portfolio_demo.public_mode', true);
        [$admin, $organisation] = $this->actor('admin');

        $this->actingAs($admin, 'web')->postJson(
            $this->collectionUrl($organisation),
            [],
            ['Idempotency-Key' => 'public-demo'],
        )->assertForbidden();

        $this->assertDatabaseCount('compliance_expiry_digest_requests', 0);
        Queue::assertNothingPushed();
    }

    public function test_worker_sends_only_aggregate_counts_and_duplicate_delivery_is_a_no_op(): void
    {
        Queue::fake();
        Notification::fake();
        [$admin, $organisation] = $this->actor('admin');
        $this->credential($organisation, '2026-09-30');
        $this->credential($organisation, '2026-10-20');
        $this->credential($organisation, '2027-01-01');
        $request = $this->actingAs($admin, 'web')->postJson(
            $this->collectionUrl($organisation),
            [],
            ['Idempotency-Key' => 'worker-success'],
        )->assertAccepted();
        $requestId = (int) $request->json('data.id');
        $this->travelTo('2026-10-04 12:00:00 UTC');

        $this->runJob($requestId);
        $this->runJob($requestId);

        $this->assertDatabaseHas('compliance_expiry_digest_requests', [
            'id' => $requestId,
            'status' => 'sent',
            'expired_count' => 1,
            'expiring_count' => 1,
        ]);
        Notification::assertSentOnDemand(ComplianceExpiryDigestNotification::class, 1);
    }

    public function test_worker_marks_permanent_recipient_failure_without_retrying(): void
    {
        Queue::fake();
        Notification::fake();
        [$admin, $organisation, $membership] = $this->actor('admin');
        $request = $this->actingAs($admin, 'web')->postJson(
            $this->collectionUrl($organisation),
            [],
            ['Idempotency-Key' => 'deactivated-admin'],
        )->assertAccepted();
        $membership->update(['deactivated_at' => now()]);

        $this->runJob((int) $request->json('data.id'));

        $this->assertDatabaseHas('compliance_expiry_digest_requests', [
            'id' => $request->json('data.id'),
            'status' => 'failed',
            'failure_code' => 'recipient_no_longer_eligible',
        ]);
        Notification::assertNothingSent();
    }

    public function test_third_transient_failure_records_safe_failure_and_still_throws_for_sqs_redrive(): void
    {
        Queue::fake();
        [$admin, $organisation] = $this->actor('admin');
        $request = $this->actingAs($admin, 'web')->postJson(
            $this->collectionUrl($organisation),
            [],
            ['Idempotency-Key' => 'transient-failure'],
        )->assertAccepted();
        $requestId = (int) $request->json('data.id');

        $notifier = Mockery::mock(ExpiryDigestNotifier::class);
        $notifier->shouldReceive('send')->once()->andThrow(new \RuntimeException('provider detail'));
        $job = new SendExpiryDigestJob($requestId);
        $queueJob = Mockery::mock(QueueJob::class);
        $queueJob->shouldReceive('attempts')->andReturn(3);
        $job->setJob($queueJob);

        try {
            $job->handle(
                $this->app->make(ExpiryDigestRequestStore::class),
                $this->app->make(ExpiryDigestSummaryReadModel::class),
                $notifier,
            );
            self::fail('The retryable exception was not propagated.');
        } catch (ExpiryDigestDeliveryException $exception) {
            self::assertSame('Expiry digest delivery failed.', $exception->getMessage());
        }

        $this->assertDatabaseHas('compliance_expiry_digest_requests', [
            'id' => $requestId,
            'status' => 'failed',
            'failure_code' => 'delivery_attempts_exhausted',
        ]);
    }

    public function test_serialized_job_payload_contains_only_the_internal_request_identifier(): void
    {
        $connection = Queue::connection('sync');
        $createPayload = new ReflectionMethod($connection, 'createPayload');
        $payload = $createPayload->invoke($connection, new SendExpiryDigestJob(42), 'compliance');

        self::assertStringContainsString('requestId', $payload);
        self::assertStringContainsString('i:42', $payload);
        self::assertStringNotContainsString('@example.test', $payload);
        self::assertStringNotContainsString('token', strtolower($payload));
        self::assertStringNotContainsString('email', strtolower($payload));
        self::assertStringNotContainsString('candidate', strtolower($payload));
        self::assertStringNotContainsString('credential_number', $payload);
        self::assertStringNotContainsString('credential_detail', $payload);
        self::assertStringNotContainsString('TenantContext', $payload);
    }

    /** @return array{User, Organisation, OrganisationMembership} */
    private function actor(string $role): array
    {
        $user = User::factory()->create();
        $organisation = Organisation::query()->create(['name' => 'Digest '.uniqid()]);
        $membership = OrganisationMembership::query()->create([
            'organisation_id' => $organisation->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
        ]);

        return [$user, $organisation, $membership];
    }

    /** @return array{User, OrganisationMembership} */
    private function membershipActor(Organisation $organisation, string $role): array
    {
        $user = User::factory()->create();
        $membership = OrganisationMembership::query()->create([
            'organisation_id' => $organisation->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
        ]);

        return [$user, $membership];
    }

    private function credential(Organisation $organisation, string $expiresOn): void
    {
        $candidate = Candidate::query()->create([
            'organisation_id' => $organisation->getKey(),
            'first_name' => 'Synthetic',
            'last_name' => uniqid(),
        ]);
        $definition = QualificationDefinition::query()->create([
            'organisation_id' => $organisation->getKey(),
            'name' => 'Synthetic qualification '.uniqid(),
            'is_active' => true,
        ]);
        CandidateQualification::query()->create([
            'organisation_id' => $organisation->getKey(),
            'candidate_id' => $candidate->getKey(),
            'qualification_definition_id' => $definition->getKey(),
            'expires_on' => $expiresOn,
        ]);
    }

    private function runJob(int $requestId): void
    {
        (new SendExpiryDigestJob($requestId))->handle(
            $this->app->make(ExpiryDigestRequestStore::class),
            $this->app->make(ExpiryDigestSummaryReadModel::class),
            $this->app->make(ExpiryDigestNotifier::class),
        );
    }

    private function collectionUrl(Organisation $organisation): string
    {
        return "/api/v1/organisations/{$organisation->getKey()}/compliance/expiry-digests";
    }

    private function statusUrl(Organisation $organisation, int $requestId): string
    {
        return $this->collectionUrl($organisation).'/'.$requestId;
    }

    private function databasePdo(): PDO
    {
        $connection = config('database.connections.pgsql');
        self::assertIsArray($connection);

        return new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', $connection['host'], $connection['port'], $connection['database']),
            (string) $connection['username'],
            (string) $connection['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}
