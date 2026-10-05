<?php

namespace App\Modules\Ai\Infrastructure\Persistence;

use App\Modules\Ai\Application\Contracts\MatchExplanationStore;
use App\Modules\Ai\Application\Data\MatchExplanationDraft;
use App\Modules\Ai\Application\Data\MatchExplanationRecord;
use App\Modules\Ai\Application\Data\MatchExplanationWork;
use App\Modules\Ai\Domain\AiOperationStatus;
use App\Modules\Audit\Application\Contracts\AuditRecorder;
use App\Modules\Audit\Application\Data\AuditEvent;
use App\Modules\Organisation\Application\Data\TenantContext;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class EloquentMatchExplanationStore implements MatchExplanationStore
{
    public function __construct(private AuditRecorder $audit) {}

    public function createOrRetrieve(TenantContext $tenant, int $jobId, int $candidateId, string $sourceFingerprint, string $provider, string $model, string $promptVersion, string $schemaVersion): MatchExplanationRecord
    {
        return DB::transaction(function () use ($tenant, $jobId, $candidateId, $sourceFingerprint, $provider, $model, $promptVersion, $schemaVersion): MatchExplanationRecord {
            $now = now('UTC');
            $created = DB::table('ai_match_explanations')->insertOrIgnore([
                'organisation_id' => $tenant->organisationId, 'job_id' => $jobId, 'candidate_id' => $candidateId,
                'requested_by_user_id' => $tenant->userId, 'source_fingerprint' => $sourceFingerprint,
                'status' => AiOperationStatus::Queued->value, 'provider' => $provider, 'model' => $model,
                'prompt_version' => $promptVersion, 'schema_version' => $schemaVersion,
                'created_at' => $now, 'updated_at' => $now,
            ]) === 1;
            $request = AiMatchExplanation::query()->where('organisation_id', $tenant->organisationId)
                ->where('job_id', $jobId)->where('candidate_id', $candidateId)
                ->where('source_fingerprint', $sourceFingerprint)->sole();
            if ($created) {
                $this->audit->record(new AuditEvent($tenant->organisationId, $tenant->userId, 'ai.match_explanation_requested', 'ai_match_explanation', (int) $request->getKey(), [
                    'job_id' => $jobId, 'candidate_id' => $candidateId,
                ]));
            }

            return self::record($request);
        });
    }

    public function find(int $organisationId, int $jobId, int $candidateId, int $explanationId): ?MatchExplanationRecord
    {
        $request = AiMatchExplanation::query()->where('organisation_id', $organisationId)
            ->where('job_id', $jobId)->where('candidate_id', $candidateId)->whereKey($explanationId)->first();

        return $request === null ? null : self::record($request);
    }

    public function beginProcessing(int $requestId): ?MatchExplanationWork
    {
        return DB::transaction(function () use ($requestId): ?MatchExplanationWork {
            $request = AiMatchExplanation::query()->lockForUpdate()->find($requestId);
            if ($request === null || in_array($request->getAttribute('status'), [AiOperationStatus::Ready, AiOperationStatus::Failed], true)) {
                return null;
            }
            $request->forceFill(['status' => AiOperationStatus::Processing, 'processing_started_at' => now('UTC')])->save();

            return new MatchExplanationWork(
                (int) $request->getKey(),
                (int) $request->getAttribute('organisation_id'),
                (int) $request->getAttribute('job_id'),
                (int) $request->getAttribute('candidate_id'),
                (string) $request->getAttribute('source_fingerprint'),
                (string) $request->getAttribute('provider'),
                (string) $request->getAttribute('model'),
            );
        });
    }

    public function markReady(int $requestId, MatchExplanationDraft $draft, ?string $providerRequestId): void
    {
        AiMatchExplanation::query()->whereKey($requestId)->where('status', AiOperationStatus::Processing->value)->update([
            'status' => AiOperationStatus::Ready->value, 'summary' => $draft->summary,
            'factors' => json_encode($draft->factors, JSON_THROW_ON_ERROR), 'provider_request_id' => $providerRequestId,
            'ready_at' => now('UTC'), 'failure_code' => null, 'failed_at' => null, 'updated_at' => now('UTC'),
        ]);
    }

    public function markFailed(int $requestId, string $failureCode): void
    {
        AiMatchExplanation::query()->whereKey($requestId)->whereIn('status', [AiOperationStatus::Queued->value, AiOperationStatus::Processing->value])->update([
            'status' => AiOperationStatus::Failed->value, 'failure_code' => $failureCode,
            'failed_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);
    }

    private static function record(AiMatchExplanation $request): MatchExplanationRecord
    {
        $status = $request->getAttribute('status');
        $createdAt = $request->getAttribute('created_at');
        if (! $status instanceof AiOperationStatus || ! $createdAt instanceof DateTimeImmutable) {
            throw new LogicException('The AI match explanation cannot be mapped.');
        }
        /** @var list<array{type: string, explanation: string}>|null $factors */
        $factors = $request->getAttribute('factors');

        return new MatchExplanationRecord(
            (int) $request->getKey(), (int) $request->getAttribute('organisation_id'), (int) $request->getAttribute('job_id'),
            (int) $request->getAttribute('candidate_id'), (string) $request->getAttribute('source_fingerprint'), $status,
            $request->getAttribute('summary'), $factors, $request->getAttribute('failure_code'), $createdAt,
        );
    }
}
