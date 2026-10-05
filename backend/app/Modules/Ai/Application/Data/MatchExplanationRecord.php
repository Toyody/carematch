<?php

namespace App\Modules\Ai\Application\Data;

use App\Modules\Ai\Domain\AiOperationStatus;
use DateTimeImmutable;

final readonly class MatchExplanationRecord
{
    /** @param list<array{type: string, explanation: string}>|null $factors */
    public function __construct(
        public int $id,
        public int $organisationId,
        public int $jobId,
        public int $candidateId,
        public string $sourceFingerprint,
        public AiOperationStatus $status,
        public ?string $summary,
        public ?array $factors,
        public ?string $failureCode,
        public DateTimeImmutable $createdAt,
    ) {}
}
