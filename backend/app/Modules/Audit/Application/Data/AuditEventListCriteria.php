<?php

namespace App\Modules\Audit\Application\Data;

use DateTimeImmutable;

final readonly class AuditEventListCriteria
{
    public function __construct(
        public ?string $eventType,
        public ?string $subjectType,
        public ?int $subjectId,
        public ?int $actorUserId,
        public ?DateTimeImmutable $occurredFrom,
        public ?DateTimeImmutable $occurredTo,
        public int $page,
        public int $perPage,
    ) {}
}
