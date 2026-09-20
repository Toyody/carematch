<?php

namespace App\Modules\Organisation\Infrastructure\Persistence;

use App\Modules\Organisation\Application\Contracts\OrganisationMembershipLister;
use App\Modules\Organisation\Application\Data\OrganisationMembershipRecord;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Organisation\Application\Exceptions\MembershipManagementForbidden;
use App\Modules\Organisation\Application\Exceptions\TenantMembershipUnavailable;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentOrganisationMembershipLister implements OrganisationMembershipLister
{
    public function forOrganisation(TenantContext $tenant): array
    {
        return DB::transaction(static function () use ($tenant): array {
            $organisation = Organisation::query()
                ->whereKey($tenant->organisationId)
                ->sharedLock()
                ->first(['id']);

            if ($organisation === null) {
                throw new TenantMembershipUnavailable;
            }

            $actor = OrganisationMembership::query()
                ->where('organisation_id', $tenant->organisationId)
                ->whereKey($tenant->membershipId)
                ->where('user_id', $tenant->userId)
                ->first(['role', 'deactivated_at']);

            if ($actor === null || $actor->getAttribute('deactivated_at') !== null) {
                throw new TenantMembershipUnavailable;
            }

            if ($actor->getAttribute('role') !== OrganisationRole::Admin->value) {
                throw new MembershipManagementForbidden;
            }

            $memberships = OrganisationMembership::query()
                ->where('organisation_id', $tenant->organisationId)
                ->orderBy('id')
                ->get(['id', 'user_id', 'role', 'deactivated_at', 'created_at', 'updated_at'])
                ->map(static fn (OrganisationMembership $membership): OrganisationMembershipRecord => self::toRecord($membership))
                ->all();

            return array_values($memberships);
        });
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
