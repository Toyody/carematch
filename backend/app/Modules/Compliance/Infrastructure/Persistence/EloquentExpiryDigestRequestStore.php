<?php

namespace App\Modules\Compliance\Infrastructure\Persistence;

use App\Modules\Audit\Application\Contracts\AuditRecorder;
use App\Modules\Audit\Application\Data\AuditEvent;
use App\Modules\Compliance\Application\Contracts\ExpiryDigestRequestStore;
use App\Modules\Compliance\Application\Data\ExpiryDigestRequestRecord;
use App\Modules\Compliance\Application\Data\ExpiryDigestRequestResult;
use App\Modules\Compliance\Application\Data\ExpiryDigestSummary;
use App\Modules\Compliance\Application\Data\ExpiryDigestWork;
use App\Modules\Compliance\Application\Exceptions\ExpiryDigestIdempotencyConflict;
use App\Modules\Compliance\Domain\ExpiryDigestStatus;
use App\Modules\Organisation\Application\Data\TenantContext;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class EloquentExpiryDigestRequestStore implements ExpiryDigestRequestStore
{
    public function __construct(private AuditRecorder $audit) {}

    public function createOrRetrieve(TenantContext $tenant, string $keyHash, string $fingerprint): ExpiryDigestRequestResult
    {
        return DB::transaction(function () use ($tenant, $keyHash, $fingerprint): ExpiryDigestRequestResult {
            $now = now('UTC');
            $created = DB::table('compliance_expiry_digest_requests')->insertOrIgnore([
                'organisation_id' => $tenant->organisationId,
                'requested_by_user_id' => $tenant->userId,
                'idempotency_key_hash' => $keyHash,
                'request_fingerprint' => $fingerprint,
                'status' => ExpiryDigestStatus::Queued->value,
                'queued_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]) === 1;

            $request = ExpiryDigestRequest::query()
                ->where('organisation_id', $tenant->organisationId)
                ->where('requested_by_user_id', $tenant->userId)
                ->where('idempotency_key_hash', $keyHash)
                ->sole();

            if (! hash_equals((string) $request->getAttribute('request_fingerprint'), $fingerprint)) {
                throw new ExpiryDigestIdempotencyConflict;
            }

            if ($created) {
                $this->audit->record(new AuditEvent(
                    $tenant->organisationId,
                    $tenant->userId,
                    'compliance.expiry_digest_requested',
                    'compliance_expiry_digest_request',
                    (int) $request->getKey(),
                ));
            }

            return new ExpiryDigestRequestResult($this->record($request), $created);
        });
    }

    public function findForOrganisation(int $organisationId, int $requestId): ?ExpiryDigestRequestRecord
    {
        $request = ExpiryDigestRequest::query()
            ->where('organisation_id', $organisationId)
            ->whereKey($requestId)
            ->first();

        return $request === null ? null : $this->record($request);
    }

    public function beginProcessing(int $requestId): ?ExpiryDigestWork
    {
        return DB::transaction(function () use ($requestId): ?ExpiryDigestWork {
            $request = ExpiryDigestRequest::query()->lockForUpdate()->find($requestId);
            if ($request === null) {
                return null;
            }

            $status = $request->getAttribute('status');
            if (! $status instanceof ExpiryDigestStatus) {
                throw new LogicException('The expiry digest status is invalid.');
            }
            if (in_array($status, [ExpiryDigestStatus::Sent, ExpiryDigestStatus::Failed], true)) {
                return null;
            }

            $request->forceFill([
                'status' => ExpiryDigestStatus::Processing,
                'processing_started_at' => now('UTC'),
            ])->save();

            return new ExpiryDigestWork(
                (int) $request->getKey(),
                (int) $request->getAttribute('organisation_id'),
                (int) $request->getAttribute('requested_by_user_id'),
            );
        });
    }

    public function markSent(int $requestId, ExpiryDigestSummary $summary): void
    {
        DB::transaction(function () use ($requestId, $summary): void {
            $request = ExpiryDigestRequest::query()->lockForUpdate()->find($requestId);
            if ($request === null || $request->getAttribute('status') !== ExpiryDigestStatus::Processing) {
                return;
            }

            $request->forceFill([
                'status' => ExpiryDigestStatus::Sent,
                'expired_count' => $summary->expiredCount,
                'expiring_count' => $summary->expiringCount,
                'failure_code' => null,
                'sent_at' => now('UTC'),
                'failed_at' => null,
            ])->save();
        });
    }

    public function markFailed(int $requestId, string $failureCode): void
    {
        DB::transaction(function () use ($requestId, $failureCode): void {
            $request = ExpiryDigestRequest::query()->lockForUpdate()->find($requestId);
            if ($request === null || $request->getAttribute('status') !== ExpiryDigestStatus::Processing) {
                return;
            }

            $request->forceFill([
                'status' => ExpiryDigestStatus::Failed,
                'expired_count' => null,
                'expiring_count' => null,
                'failure_code' => $failureCode,
                'sent_at' => null,
                'failed_at' => now('UTC'),
            ])->save();
        });
    }

    private function record(ExpiryDigestRequest $request): ExpiryDigestRequestRecord
    {
        $status = $request->getAttribute('status');
        $queuedAt = $request->getAttribute('queued_at');
        if (! $status instanceof ExpiryDigestStatus || ! $queuedAt instanceof DateTimeImmutable) {
            throw new LogicException('The expiry digest request cannot be mapped.');
        }

        return new ExpiryDigestRequestRecord(
            (int) $request->getKey(),
            (int) $request->getAttribute('organisation_id'),
            (int) $request->getAttribute('requested_by_user_id'),
            $status,
            $request->getAttribute('expired_count') === null ? null : (int) $request->getAttribute('expired_count'),
            $request->getAttribute('expiring_count') === null ? null : (int) $request->getAttribute('expiring_count'),
            $request->getAttribute('failure_code') === null ? null : (string) $request->getAttribute('failure_code'),
            $queuedAt,
            $request->getAttribute('processing_started_at'),
            $request->getAttribute('sent_at'),
            $request->getAttribute('failed_at'),
        );
    }
}
