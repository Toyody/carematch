<?php

namespace App\Modules\Organisation\Infrastructure\Persistence;

use App\Modules\Organisation\Application\Contracts\OrganisationNameUpdater;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\OrganisationSummary;
use DateTimeImmutable;
use DateTimeInterface;
use LogicException;

final class EloquentOrganisationNameUpdater implements OrganisationNameUpdater
{
    public function updateName(
        int $organisationId,
        string $name,
        OrganisationRole $role,
    ): OrganisationSummary {
        $organisation = Organisation::query()->findOrFail($organisationId);
        $organisation->update(['name' => $name]);
        $createdAt = $organisation->getAttribute('created_at');

        if (! $createdAt instanceof DateTimeInterface) {
            throw new LogicException('The organisation has no creation timestamp.');
        }

        return new OrganisationSummary(
            id: (int) $organisation->getKey(),
            name: (string) $organisation->getAttribute('name'),
            role: $role->value,
            createdAt: DateTimeImmutable::createFromInterface($createdAt),
        );
    }
}
