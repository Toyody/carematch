<?php

namespace App\Modules\Organisation\Infrastructure\Persistence;

use App\Modules\Organisation\Application\Contracts\ActiveOrganisationMemberships;
use App\Modules\Organisation\Application\Data\OrganisationSummary;
use DateTimeImmutable;
use DateTimeInterface;
use LogicException;

final class EloquentActiveOrganisationMemberships implements ActiveOrganisationMemberships
{
    public function forUser(int $userId): array
    {
        $organisations = Organisation::query()
            ->join(
                'organisation_memberships as membership',
                'membership.organisation_id',
                '=',
                'organisations.id',
            )
            ->where('membership.user_id', $userId)
            ->whereNull('membership.deactivated_at')
            ->orderBy('organisations.name')
            ->orderBy('organisations.id')
            ->get([
                'organisations.id',
                'organisations.name',
                'organisations.created_at',
                'membership.role as membership_role',
            ]);

        return array_values($organisations->map(function (Organisation $organisation): OrganisationSummary {
            $createdAt = $organisation->getAttribute('created_at');

            if (! $createdAt instanceof DateTimeInterface) {
                throw new LogicException('The organisation has no creation timestamp.');
            }

            return new OrganisationSummary(
                id: (int) $organisation->getKey(),
                name: (string) $organisation->getAttribute('name'),
                role: (string) $organisation->getAttribute('membership_role'),
                createdAt: DateTimeImmutable::createFromInterface($createdAt),
            );
        })->all());
    }
}
