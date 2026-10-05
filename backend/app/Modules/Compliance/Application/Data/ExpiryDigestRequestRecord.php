<?php

namespace App\Modules\Compliance\Application\Data;

use App\Modules\Compliance\Domain\ExpiryDigestStatus;
use DateTimeImmutable;

final readonly class ExpiryDigestRequestRecord
{
    public function __construct(
        public int $id,
        public int $organisationId,
        public int $requestedByUserId,
        public ExpiryDigestStatus $status,
        public ?int $expiredCount,
        public ?int $expiringCount,
        public ?string $failureCode,
        public DateTimeImmutable $queuedAt,
        public ?DateTimeImmutable $processingStartedAt,
        public ?DateTimeImmutable $sentAt,
        public ?DateTimeImmutable $failedAt,
    ) {}
}
