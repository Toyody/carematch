<?php

namespace App\Modules\Ai\Application\Contracts;

use App\Modules\Ai\Application\Data\MatchExplanationDraft;
use App\Modules\Ai\Application\Data\MatchExplanationRecord;
use App\Modules\Ai\Application\Data\MatchExplanationWork;
use App\Modules\Organisation\Application\Data\TenantContext;

interface MatchExplanationStore
{
    public function createOrRetrieve(
        TenantContext $tenant,
        int $jobId,
        int $candidateId,
        string $sourceFingerprint,
        string $provider,
        string $model,
        string $promptVersion,
        string $schemaVersion,
    ): MatchExplanationRecord;

    public function find(int $organisationId, int $jobId, int $candidateId, int $explanationId): ?MatchExplanationRecord;

    public function beginProcessing(int $requestId): ?MatchExplanationWork;

    public function markReady(int $requestId, MatchExplanationDraft $draft, ?string $providerRequestId): void;

    public function markFailed(int $requestId, string $failureCode): void;
}
