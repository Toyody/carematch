<?php

namespace App\Modules\Audit\Application\Data;

use DateTimeImmutable;

final readonly class AuditEvent
{
    /** @param array<string, bool|int|string|list<string>|null> $metadata */
    public function __construct(
        public int $organisationId,
        public int $actorUserId,
        public string $eventType,
        public string $subjectType,
        public int $subjectId,
        public array $metadata = [],
        public ?DateTimeImmutable $occurredAt = null,
    ) {}
}
