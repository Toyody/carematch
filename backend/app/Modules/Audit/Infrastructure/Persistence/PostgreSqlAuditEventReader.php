<?php

namespace App\Modules\Audit\Infrastructure\Persistence;

use App\Modules\Audit\Application\Contracts\AuditEventReader;
use App\Modules\Audit\Application\Data\AuditEventListCriteria;
use App\Modules\Audit\Application\Data\StoredAuditEvent;
use App\Modules\Audit\Application\Data\StoredAuditEventPage;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final readonly class PostgreSqlAuditEventReader implements AuditEventReader
{
    public function list(int $organisationId, AuditEventListCriteria $criteria): StoredAuditEventPage
    {
        $query = DB::table('audit_events')->where('organisation_id', $organisationId);
        $this->filters($query, $criteria);
        $paginator = $query->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate($criteria->perPage, ['*'], 'page', $criteria->page);

        /** @var list<StoredAuditEvent> $items */
        $items = array_map($this->record(...), $paginator->items());

        return new StoredAuditEventPage($items, $paginator->currentPage(), $paginator->lastPage(), $paginator->perPage(), $paginator->total());
    }

    private function filters(Builder $query, AuditEventListCriteria $criteria): void
    {
        $query->when($criteria->eventType, static fn (Builder $q, string $value): Builder => $q->where('event_type', $value));
        $query->when($criteria->subjectType, static fn (Builder $q, string $value): Builder => $q->where('subject_type', $value));
        $query->when($criteria->subjectId, static fn (Builder $q, int $value): Builder => $q->where('subject_id', $value));
        $query->when($criteria->actorUserId, static fn (Builder $q, int $value): Builder => $q->where('actor_user_id', $value));
        $query->when($criteria->occurredFrom, static fn (Builder $q, DateTimeImmutable $value): Builder => $q->where('occurred_at', '>=', $value));
        $query->when($criteria->occurredTo, static fn (Builder $q, DateTimeImmutable $value): Builder => $q->where('occurred_at', '<=', $value));
    }

    private function record(stdClass $row): StoredAuditEvent
    {
        $metadata = json_decode((string) $row->metadata, true, 512, JSON_THROW_ON_ERROR);

        return new StoredAuditEvent(
            id: (int) $row->id,
            organisationId: (int) $row->organisation_id,
            actorUserId: (int) $row->actor_user_id,
            eventType: (string) $row->event_type,
            subjectType: (string) $row->subject_type,
            subjectId: (int) $row->subject_id,
            metadata: is_array($metadata) ? $metadata : [],
            occurredAt: new DateTimeImmutable((string) $row->occurred_at),
        );
    }
}
