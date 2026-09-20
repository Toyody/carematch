<?php

namespace App\Modules\Organisation\Infrastructure\Persistence;

use App\Modules\Organisation\Application\Contracts\OrganisationCreator;
use App\Modules\Organisation\Application\Data\OrganisationSummary;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentOrganisationCreator implements OrganisationCreator
{
    public function createWithInitialAdmin(string $name, int $creatorUserId): OrganisationSummary
    {
        return DB::transaction(function () use ($name, $creatorUserId): OrganisationSummary {
            $organisation = Organisation::query()->create([
                'name' => $name,
            ]);

            OrganisationMembership::query()->create([
                'organisation_id' => $organisation->getKey(),
                'user_id' => $creatorUserId,
                'role' => 'admin',
            ]);

            $createdAt = $organisation->getAttribute('created_at');

            if (! $createdAt instanceof DateTimeInterface) {
                throw new LogicException('The organisation has no creation timestamp.');
            }

            return new OrganisationSummary(
                id: (int) $organisation->getKey(),
                name: (string) $organisation->getAttribute('name'),
                role: 'admin',
                createdAt: DateTimeImmutable::createFromInterface($createdAt),
            );
        });
    }
}
