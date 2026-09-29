<?php

namespace App\Modules\Organisation\Infrastructure\Persistence;

use App\Modules\Audit\Application\Contracts\AuditRecorder;
use App\Modules\Audit\Application\Data\AuditEvent;
use App\Modules\Organisation\Application\Contracts\OrganisationInvitationRevoker;
use App\Modules\Organisation\Application\Exceptions\AcceptedInvitationCannotBeRevoked;
use App\Modules\Organisation\Application\Exceptions\InvitationNotFound;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final readonly class EloquentOrganisationInvitationRevoker implements OrganisationInvitationRevoker
{
    public function __construct(private AuditRecorder $audit) {}

    public function revoke(
        int $organisationId,
        int $actorUserId,
        int $invitationId,
        DateTimeImmutable $revokedAt,
    ): void {
        DB::transaction(function () use ($organisationId, $actorUserId, $invitationId, $revokedAt): void {
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
            $this->audit->record(new AuditEvent($organisationId, $actorUserId, 'invitation.revoked', 'invitation', $invitationId));
        });
    }
}
