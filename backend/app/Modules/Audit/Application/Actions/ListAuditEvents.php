<?php

namespace App\Modules\Audit\Application\Actions;

use App\Modules\Audit\Application\Contracts\AuditEventReader;
use App\Modules\Audit\Application\Data\AuditEventListCriteria;
use App\Modules\Audit\Application\Data\AuditEventPage;
use App\Modules\Audit\Application\Data\AuditEventRecord;
use App\Modules\Audit\Application\Data\StoredAuditEvent;
use App\Modules\Identity\Application\Contracts\IdentityUserLookup;
use App\Modules\Organisation\Application\Data\TenantContext;
use LogicException;

final readonly class ListAuditEvents
{
    public function __construct(
        private AuditEventReader $events,
        private IdentityUserLookup $users,
    ) {}

    public function handle(TenantContext $tenant, AuditEventListCriteria $criteria): AuditEventPage
    {
        $page = $this->events->list($tenant->organisationId, $criteria);
        $users = $this->users->findByIds(array_values(array_unique(array_map(
            static fn (StoredAuditEvent $event): int => $event->actorUserId,
            $page->items,
        ))));

        $items = array_map(static function (StoredAuditEvent $event) use ($users): AuditEventRecord {
            $user = $users[$event->actorUserId] ?? null;
            if ($user === null) {
                throw new LogicException('An audit event references a missing identity user.');
            }

            return new AuditEventRecord(
                id: $event->id,
                organisationId: $event->organisationId,
                actorUserId: $event->actorUserId,
                actorName: $user->name,
                actorEmail: $user->email,
                eventType: $event->eventType,
                subjectType: $event->subjectType,
                subjectId: $event->subjectId,
                metadata: $event->metadata,
                occurredAt: $event->occurredAt,
            );
        }, $page->items);

        return new AuditEventPage($items, $page->currentPage, $page->lastPage, $page->perPage, $page->total);
    }
}
