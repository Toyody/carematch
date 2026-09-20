<?php

namespace App\Modules\Organisation\Infrastructure\Persistence;

use App\Modules\Organisation\Application\Contracts\OrganisationMembershipMutator;
use App\Modules\Organisation\Application\Data\OrganisationMembershipRecord;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Organisation\Application\Exceptions\DeactivatedMembershipRoleConflict;
use App\Modules\Organisation\Application\Exceptions\LastActiveAdminConflict;
use App\Modules\Organisation\Application\Exceptions\MembershipManagementForbidden;
use App\Modules\Organisation\Application\Exceptions\OrganisationMembershipNotFound;
use App\Modules\Organisation\Application\Exceptions\TenantMembershipUnavailable;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentOrganisationMembershipMutator implements OrganisationMembershipMutator
{
    public function changeRole(
        TenantContext $tenant,
        int $membershipId,
        OrganisationRole $role,
    ): OrganisationMembershipRecord {
        return DB::transaction(function () use ($tenant, $membershipId, $role): OrganisationMembershipRecord {
            [$actor, $target] = $this->lockActorAndTarget($tenant, $membershipId);

            if ($target->getAttribute('deactivated_at') !== null) {
                throw new DeactivatedMembershipRoleConflict;
            }

            $currentRole = OrganisationRole::from((string) $target->getAttribute('role'));

            if ($currentRole === $role) {
                return self::toRecord($target);
            }

            if ($currentRole === OrganisationRole::Admin && $role !== OrganisationRole::Admin) {
                $this->assertAnotherActiveAdminExists($tenant->organisationId, (int) $target->getKey());
            }

            $target->forceFill(['role' => $role->value])->save();

            return self::toRecord($target);
        });
    }

    public function deactivate(
        TenantContext $tenant,
        int $membershipId,
        DateTimeImmutable $deactivatedAt,
    ): void {
        DB::transaction(function () use ($tenant, $membershipId, $deactivatedAt): void {
            [, $target] = $this->lockActorAndTarget($tenant, $membershipId);

            if ($target->getAttribute('deactivated_at') !== null) {
                return;
            }

            if ($target->getAttribute('role') === OrganisationRole::Admin->value) {
                $this->assertAnotherActiveAdminExists($tenant->organisationId, (int) $target->getKey());
            }

            $target->forceFill(['deactivated_at' => $deactivatedAt])->save();
        });
    }

    /**
     * @return array{OrganisationMembership, OrganisationMembership}
     */
    private function lockActorAndTarget(
        TenantContext $tenant,
        int $membershipId,
    ): array {
        $organisation = Organisation::query()
            ->whereKey($tenant->organisationId)
            ->lockForUpdate()
            ->first(['id']);

        if ($organisation === null) {
            throw new TenantMembershipUnavailable;
        }

        $membershipIds = array_values(array_unique([$tenant->membershipId, $membershipId]));
        sort($membershipIds);

        /** @var Collection<int, OrganisationMembership> $memberships */
        $memberships = OrganisationMembership::query()
            ->where('organisation_id', $tenant->organisationId)
            ->whereIn('id', $membershipIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $actor = $memberships->firstWhere('id', $tenant->membershipId);

        if (! $actor instanceof OrganisationMembership
            || (int) $actor->getAttribute('user_id') !== $tenant->userId
            || $actor->getAttribute('deactivated_at') !== null) {
            throw new TenantMembershipUnavailable;
        }

        if ($actor->getAttribute('role') !== OrganisationRole::Admin->value) {
            throw new MembershipManagementForbidden;
        }

        $target = $memberships->firstWhere('id', $membershipId);

        if (! $target instanceof OrganisationMembership) {
            throw new OrganisationMembershipNotFound;
        }

        return [$actor, $target];
    }

    private function assertAnotherActiveAdminExists(int $organisationId, int $excludedMembershipId): void
    {
        $anotherAdminExists = OrganisationMembership::query()
            ->where('organisation_id', $organisationId)
            ->where('role', OrganisationRole::Admin->value)
            ->whereNull('deactivated_at')
            ->whereKeyNot($excludedMembershipId)
            ->exists();

        if (! $anotherAdminExists) {
            throw new LastActiveAdminConflict;
        }
    }

    private static function toRecord(OrganisationMembership $membership): OrganisationMembershipRecord
    {
        return new OrganisationMembershipRecord(
            id: (int) $membership->getKey(),
            userId: (int) $membership->getAttribute('user_id'),
            role: OrganisationRole::from((string) $membership->getAttribute('role')),
            deactivatedAt: self::optionalDate($membership, 'deactivated_at'),
            createdAt: self::requiredDate($membership, 'created_at'),
            updatedAt: self::requiredDate($membership, 'updated_at'),
        );
    }

    private static function requiredDate(OrganisationMembership $membership, string $attribute): DateTimeImmutable
    {
        $value = $membership->getAttribute($attribute);

        if (! $value instanceof DateTimeInterface) {
            throw new LogicException("The membership has no {$attribute} timestamp.");
        }

        return DateTimeImmutable::createFromInterface($value);
    }

    private static function optionalDate(OrganisationMembership $membership, string $attribute): ?DateTimeImmutable
    {
        $value = $membership->getAttribute($attribute);

        if ($value === null) {
            return null;
        }

        if (! $value instanceof DateTimeInterface) {
            throw new LogicException("The membership has an invalid {$attribute} timestamp.");
        }

        return DateTimeImmutable::createFromInterface($value);
    }
}
