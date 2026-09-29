<?php

namespace App\Modules\Audit\Application\Data;

use DateTimeImmutable;

final readonly class StoredAuditEvent
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public int $id,
        public int $organisationId,
        public int $actorUserId,
        public string $eventType,
        public string $subjectType,
        public int $subjectId,
        public array $metadata,
        public DateTimeImmutable $occurredAt,
    ) {}
}
