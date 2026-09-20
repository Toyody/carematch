<?php

namespace App\Modules\Organisation\Infrastructure\Persistence;

use App\Modules\Organisation\Application\Contracts\OrganisationInvitationRevoker;
use App\Modules\Organisation\Application\Exceptions\AcceptedInvitationCannotBeRevoked;
use App\Modules\Organisation\Application\Exceptions\InvitationNotFound;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentOrganisationInvitationRevoker implements OrganisationInvitationRevoker
{
    public function revoke(
        int $organisationId,
        int $invitationId,
        DateTimeImmutable $revokedAt,
    ): void {
        DB::transaction(static function () use ($organisationId, $invitationId, $revokedAt): void {
            $invitation = OrganisationInvitation::query()
                ->where('organisation_id', $organisationId)
                ->whereKey($invitationId)
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                throw new InvitationNotFound;
            }

            if ($invitation->getAttribute('accepted_at') !== null) {
                throw new AcceptedInvitationCannotBeRevoked;
            }

            if ($invitation->getAttribute('revoked_at') !== null) {
                return;
            }

            $invitation->forceFill(['revoked_at' => $revokedAt])->save();
        });
    }
}
