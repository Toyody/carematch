<?php

namespace App\Modules\Ai\Application\Data;

use App\Modules\Ai\Domain\AiOperationStatus;
use DateTimeImmutable;

final readonly class CvExtractionRecord
{
    /** @param array<string, ?string>|null $draft */
    public function __construct(
        public int $id,
        public int $organisationId,
        public int $candidateId,
        public int $candidateDocumentId,
        public int $requestedByUserId,
        public AiOperationStatus $status,
        public string $provider,
        public string $model,
        public string $promptVersion,
        public string $schemaVersion,
        public ?array $draft,
        public ?DateTimeImmutable $candidateVersion,
        public ?string $candidateFingerprint,
        public ?string $failureCode,
        public ?DateTimeImmutable $appliedAt,
        public DateTimeImmutable $createdAt,
    ) {}
}
