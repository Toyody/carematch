<?php

namespace App\Modules\Organisation\Infrastructure\Persistence;

use App\Modules\Organisation\Application\Contracts\OrganisationInvitationLister;
use App\Modules\Organisation\Application\Data\OrganisationInvitationSummary;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use DateTimeImmutable;
use DateTimeInterface;
use LogicException;

final class EloquentOrganisationInvitationLister implements OrganisationInvitationLister
{
    public function forOrganisation(int $organisationId): array
    {
        $invitations = OrganisationInvitation::query()
            ->where('organisation_id', $organisationId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'email',
                'role',
                'expires_at',
                'accepted_at',
                'revoked_at',
                'created_at',
            ])
            ->map(static fn (OrganisationInvitation $invitation): OrganisationInvitationSummary => self::toSummary($invitation))
            ->all();

        return array_values($invitations);
    }

    private static function toSummary(OrganisationInvitation $invitation): OrganisationInvitationSummary
    {
        $expiresAt = self::requiredDate($invitation, 'expires_at');
        $createdAt = self::requiredDate($invitation, 'created_at');

        return new OrganisationInvitationSummary(
            id: (int) $invitation->getKey(),
            email: (string) $invitation->getAttribute('email'),
            role: OrganisationRole::from((string) $invitation->getAttribute('role')),
            expiresAt: DateTimeImmutable::createFromInterface($expiresAt),
            acceptedAt: self::optionalDate($invitation, 'accepted_at'),
            revokedAt: self::optionalDate($invitation, 'revoked_at'),
            createdAt: DateTimeImmutable::createFromInterface($createdAt),
        );
    }

    private static function requiredDate(
        OrganisationInvitation $invitation,
        string $attribute,
    ): DateTimeInterface {
        $value = $invitation->getAttribute($attribute);

        if (! $value instanceof DateTimeInterface) {
            throw new LogicException("The invitation has no {$attribute} timestamp.");
        }

        return $value;
    }

    private static function optionalDate(
        OrganisationInvitation $invitation,
        string $attribute,
    ): ?DateTimeImmutable {
        $value = $invitation->getAttribute($attribute);

        if ($value === null) {
            return null;
        }

        if (! $value instanceof DateTimeInterface) {
            throw new LogicException("The invitation has an invalid {$attribute} timestamp.");
        }

        return DateTimeImmutable::createFromInterface($value);
    }
}
