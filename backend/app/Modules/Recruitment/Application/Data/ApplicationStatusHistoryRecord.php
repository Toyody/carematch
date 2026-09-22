<?php

namespace App\Modules\Recruitment\Application\Data;

use App\Modules\Recruitment\Domain\ApplicationStatus;
use DateTimeImmutable;

final readonly class ApplicationStatusHistoryRecord
{
    public function __construct(
        public int $id,
        public ?ApplicationStatus $fromStatus,
        public ApplicationStatus $toStatus,
        public int $changedByUserId,
        public ?string $note,
        public DateTimeImmutable $createdAt,
    ) {}
}
