<?php

namespace App\Modules\Ai\Infrastructure\Persistence;

use App\Modules\Ai\Application\Contracts\CvExtractionStore;
use App\Modules\Ai\Application\Data\CvExtractionDraft;
use App\Modules\Ai\Application\Data\CvExtractionRecord;
use App\Modules\Ai\Application\Data\CvExtractionWork;
use App\Modules\Ai\Application\Exceptions\AiIdempotencyConflict;
use App\Modules\Ai\Domain\AiOperationStatus;
use App\Modules\Audit\Application\Contracts\AuditRecorder;
use App\Modules\Audit\Application\Data\AuditEvent;
use App\Modules\Organisation\Application\Data\TenantContext;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class EloquentCvExtractionStore implements CvExtractionStore
{
    public function __construct(private AuditRecorder $audit) {}

    public function createOrRetrieve(TenantContext $tenant, int $candidateId, int $documentId, string $keyHash, string $fingerprint, string $provider, string $model, string $promptVersion, string $schemaVersion): CvExtractionRecord
    {
        return DB::transaction(function () use ($tenant, $candidateId, $documentId, $keyHash, $fingerprint, $provider, $model, $promptVersion, $schemaVersion): CvExtractionRecord {
            $now = now('UTC');
            $created = DB::table('ai_cv_extractions')->insertOrIgnore([
                'organisation_id' => $tenant->organisationId,
                'candidate_id' => $candidateId,
                'candidate_document_id' => $documentId,
                'requested_by_user_id' => $tenant->userId,
                'idempotency_key_hash' => $keyHash,
                'request_fingerprint' => $fingerprint,
                'status' => AiOperationStatus::Queued->value,
                'provider' => $provider,
                'model' => $model,
                'prompt_version' => $promptVersion,
                'schema_version' => $schemaVersion,
                'created_at' => $now,
                'updated_at' => $now,
            ]) === 1;
            $request = AiCvExtraction::query()
                ->where('organisation_id', $tenant->organisationId)
                ->where('requested_by_user_id', $tenant->userId)
                ->where('idempotency_key_hash', $keyHash)->sole();
            if (! hash_equals((string) $request->getAttribute('request_fingerprint'), $fingerprint)) {
                throw new AiIdempotencyConflict('The idempotency key has already been used for another AI extraction.');
            }
            if ($created) {
                $this->audit->record(new AuditEvent($tenant->organisationId, $tenant->userId, 'ai.cv_extraction_requested', 'ai_cv_extraction', (int) $request->getKey(), [
                    'candidate_id' => $candidateId, 'candidate_document_id' => $documentId,
                ]));
            }

            return self::record($request);
        });
    }

    public function find(int $organisationId, int $candidateId, int $documentId, int $extractionId, bool $lock = false): ?CvExtractionRecord
    {
        $query = AiCvExtraction::query()->where('organisation_id', $organisationId)
            ->where('candidate_id', $candidateId)->where('candidate_document_id', $documentId)->whereKey($extractionId);
        $request = $lock ? $query->lockForUpdate()->first() : $query->first();

        return $request === null ? null : self::record($request);
    }

    public function beginProcessing(int $requestId): ?CvExtractionWork
    {
        return DB::transaction(function () use ($requestId): ?CvExtractionWork {
            $request = AiCvExtraction::query()->lockForUpdate()->find($requestId);
            if ($request === null || in_array($request->getAttribute('status'), [AiOperationStatus::ReviewReady, AiOperationStatus::Applied, AiOperationStatus::Failed], true)) {
                return null;
            }
            $request->forceFill(['status' => AiOperationStatus::Processing, 'processing_started_at' => now('UTC')])->save();

            return new CvExtractionWork(
                (int) $request->getKey(),
                (int) $request->getAttribute('organisation_id'),
                (int) $request->getAttribute('candidate_id'),
                (int) $request->getAttribute('candidate_document_id'),
                (string) $request->getAttribute('provider'),
                (string) $request->getAttribute('model'),
            );
        });
    }

    public function markReviewReady(int $requestId, CvExtractionDraft $draft, DateTimeImmutable $candidateVersion, string $candidateFingerprint, ?string $providerRequestId): void
    {
        AiCvExtraction::query()->whereKey($requestId)->where('status', AiOperationStatus::Processing->value)->update([
            'status' => AiOperationStatus::ReviewReady->value, 'draft' => json_encode($draft->toArray(), JSON_THROW_ON_ERROR),
            'candidate_version' => $candidateVersion, 'provider_request_id' => $providerRequestId,
            'candidate_fingerprint' => $candidateFingerprint,
            'review_ready_at' => now('UTC'), 'failure_code' => null, 'failed_at' => null, 'updated_at' => now('UTC'),
        ]);
    }

    public function markFailed(int $requestId, string $failureCode): void
    {
        AiCvExtraction::query()->whereKey($requestId)->whereIn('status', [AiOperationStatus::Queued->value, AiOperationStatus::Processing->value])->update([
            'status' => AiOperationStatus::Failed->value, 'failure_code' => $failureCode,
            'failed_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);
    }

    public function markApplied(int $requestId, int $actorUserId): void
    {
        $request = AiCvExtraction::query()->lockForUpdate()->find($requestId);
        if ($request === null || $request->getAttribute('status') !== AiOperationStatus::ReviewReady) {
            throw new LogicException('The AI extraction is not available for review.');
        }
        $request->forceFill(['status' => AiOperationStatus::Applied, 'applied_by_user_id' => $actorUserId, 'applied_at' => now('UTC')])->save();
        $this->audit->record(new AuditEvent((int) $request->getAttribute('organisation_id'), $actorUserId, 'ai.cv_extraction_applied', 'ai_cv_extraction', $requestId));
    }

    private static function record(AiCvExtraction $request): CvExtractionRecord
    {
        $status = $request->getAttribute('status');
        $createdAt = $request->getAttribute('created_at');
        if (! $status instanceof AiOperationStatus || ! $createdAt instanceof DateTimeImmutable) {
            throw new LogicException('The AI extraction cannot be mapped.');
        }
        /** @var array<string, ?string>|null $draft */
        $draft = $request->getAttribute('draft');

        return new CvExtractionRecord(
            (int) $request->getKey(), (int) $request->getAttribute('organisation_id'), (int) $request->getAttribute('candidate_id'),
            (int) $request->getAttribute('candidate_document_id'), (int) $request->getAttribute('requested_by_user_id'),
            $status, (string) $request->getAttribute('provider'), (string) $request->getAttribute('model'),
            (string) $request->getAttribute('prompt_version'), (string) $request->getAttribute('schema_version'),
            $draft, $request->getAttribute('candidate_version'), $request->getAttribute('candidate_fingerprint'), $request->getAttribute('failure_code'),
            $request->getAttribute('applied_at'), $createdAt,
        );
    }
}
