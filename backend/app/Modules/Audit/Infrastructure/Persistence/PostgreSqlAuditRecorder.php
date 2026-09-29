<?php

namespace App\Modules\Audit\Infrastructure\Persistence;

use App\Modules\Audit\Application\Contracts\AuditRecorder;
use App\Modules\Audit\Application\Data\AuditEvent;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

final readonly class PostgreSqlAuditRecorder implements AuditRecorder
{
    public function record(AuditEvent $event): void
    {
        DB::table('audit_events')->insert([
            'organisation_id' => $event->organisationId,
            'actor_user_id' => $event->actorUserId,
            'event_type' => $event->eventType,
            'subject_type' => $event->subjectType,
            'subject_id' => $event->subjectId,
            'metadata' => json_encode($event->metadata === [] ? new \stdClass : $event->metadata, JSON_THROW_ON_ERROR),
            'occurred_at' => ($event->occurredAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.uP'),
        ]);
    }
}
