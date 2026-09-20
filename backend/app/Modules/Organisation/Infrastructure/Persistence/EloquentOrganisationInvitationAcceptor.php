<?php

namespace App\Modules\Organisation\Infrastructure\Persistence;

use App\Modules\Organisation\Application\Contracts\OrganisationInvitationAcceptor;
use App\Modules\Organisation\Application\Exceptions\InvitationUnavailable;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final class EloquentOrganisationInvitationAcceptor implements OrganisationInvitationAcceptor
{
    public function accept(
        string $tokenHash,
        int $userId,
        string $canonicalEmail,
        DateTimeImmutable $acceptedAt,
    ): void {
        DB::transaction(static function () use (
            $tokenHash,
            $userId,
            $canonicalEmail,
            $acceptedAt,
        ): void {
            $organisationId = OrganisationInvitation::query()
                ->where('token_hash', $tokenHash)
                ->value('organisation_id');

            if (! is_int($organisationId)) {
                throw new InvitationUnavailable;
            }

            Organisation::query()
                ->whereKey($organisationId)
                ->lockForUpdate()
                ->firstOrFail(['id']);

            $invitation = OrganisationInvitation::query()
                ->where('organisation_id', $organisationId)
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if ($invitation === null
                || ! self::isAvailableTo($invitation, $canonicalEmail, $acceptedAt)) {
                throw new InvitationUnavailable;
            }

            $membership = OrganisationMembership::query()
                ->where('organisation_id', $organisationId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($membership !== null && $membership->getAttribute('deactivated_at') === null) {
                throw new InvitationUnavailable;
            }

            $membershipValues = [
                'role' => (string) $invitation->getAttribute('role'),
                'deactivated_at' => null,
            ];

            if ($membership === null) {
                OrganisationMembership::query()->create([
                    'organisation_id' => $organisationId,
                    'user_id' => $userId,
                    ...$membershipValues,
                ]);
            } else {
                $membership->forceFill($membershipValues)->save();
            }

            $invitation->forceFill(['accepted_at' => $acceptedAt])->save();
        });
    }

    private static function isAvailableTo(
        OrganisationInvitation $invitation,
        string $canonicalEmail,
        DateTimeImmutable $acceptedAt,
    ): bool {
        if ($invitation->getAttribute('accepted_at') !== null
            || $invitation->getAttribute('revoked_at') !== null
            || $invitation->getAttribute('email') !== $canonicalEmail) {
            return false;
        }

        $expiresAt = $invitation->getAttribute('expires_at');

        return $expiresAt instanceof DateTimeInterface
            && $expiresAt->getTimestamp() > $acceptedAt->getTimestamp();
    }
}
