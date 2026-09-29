<?php

namespace App\Modules\Audit\Interfaces\Http\Resources;

use App\Modules\Audit\Application\Data\AuditEventRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditEventRecord */
final class AuditEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var AuditEventRecord $event */
        $event = $this->resource;

        return [
            'id' => $event->id,
            'actor' => ['id' => $event->actorUserId, 'name' => $event->actorName, 'email' => $event->actorEmail],
            'event_type' => $event->eventType,
            'subject' => ['type' => $event->subjectType, 'id' => $event->subjectId],
            'metadata' => $event->metadata,
            'occurred_at' => $event->occurredAt->format(DATE_ATOM),
        ];
    }
}
