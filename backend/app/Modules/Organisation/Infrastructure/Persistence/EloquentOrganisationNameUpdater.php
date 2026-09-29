<?php

namespace App\Modules\Organisation\Infrastructure\Persistence;

use App\Modules\Audit\Application\Contracts\AuditRecorder;
use App\Modules\Audit\Application\Data\AuditEvent;
use App\Modules\Organisation\Application\Contracts\OrganisationNameUpdater;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\OrganisationSummary;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class EloquentOrganisationNameUpdater implements OrganisationNameUpdater
{
    public function __construct(private AuditRecorder $audit) {}

    public function updateName(
        int $organisationId,
        int $actorUserId,
        string $name,
        OrganisationRole $role,
    ): OrganisationSummary {
        return DB::transaction(function () use ($organisationId, $actorUserId, $name, $role): OrganisationSummary {
            $organisation = Organisation::query()->whereKey($organisationId)->lockForUpdate()->firstOrFail();
            $organisation->fill(['name' => $name]);
            if ($organisation->isDirty('name')) {
                $organisation->save();
                $this->audit->record(new AuditEvent($organisationId, $actorUserId, 'organisation.updated', 'organisation', $organisationId, ['changed_fields' => ['name']]));
            }
            $createdAt = $organisation->getAttribute('created_at');

            if (! $createdAt instanceof DateTimeInterface) {
                throw new LogicException('The organisation has no creation timestamp.');
            }

            return new OrganisationSummary(
                id: (int) $organisation->getKey(), name: (string) $organisation->getAttribute('name'),
                role: $role->value, createdAt: DateTimeImmutable::createFromInterface($createdAt),
            );
        });
    }
}
