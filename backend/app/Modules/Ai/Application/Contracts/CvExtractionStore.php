<?php

namespace App\Modules\Ai\Application\Contracts;

use App\Modules\Ai\Application\Data\CvExtractionDraft;
use App\Modules\Ai\Application\Data\CvExtractionRecord;
use App\Modules\Ai\Application\Data\CvExtractionWork;
use App\Modules\Organisation\Application\Data\TenantContext;
use DateTimeImmutable;

interface CvExtractionStore
{
    public function createOrRetrieve(
        TenantContext $tenant,
        int $candidateId,
        int $documentId,
        string $keyHash,
        string $fingerprint,
        string $provider,
        string $model,
        string $promptVersion,
        string $schemaVersion,
    ): CvExtractionRecord;

    public function find(int $organisationId, int $candidateId, int $documentId, int $extractionId, bool $lock = false): ?CvExtractionRecord;

    public function beginProcessing(int $requestId): ?CvExtractionWork;

    public function markReviewReady(int $requestId, CvExtractionDraft $draft, DateTimeImmutable $candidateVersion, string $candidateFingerprint, ?string $providerRequestId): void;

    public function markFailed(int $requestId, string $failureCode): void;

    public function markApplied(int $requestId, int $actorUserId): void;
}
