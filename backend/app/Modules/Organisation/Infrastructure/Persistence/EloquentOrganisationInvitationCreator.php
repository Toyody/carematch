<?php

namespace App\Modules\Organisation\Infrastructure\Persistence;

use App\Modules\Organisation\Application\Contracts\OrganisationInvitationCreator;
use App\Modules\Organisation\Application\Data\OrganisationInvitationSummary;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Exceptions\ActiveMemberCannotBeInvited;
use App\Modules\Organisation\Application\Exceptions\PendingInvitationExists;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentOrganisationInvitationCreator implements OrganisationInvitationCreator
{
    private const UNRESOLVED_UNIQUE_CONSTRAINT = 'org_invitations_unresolved_unique';

    public function create(
        int $organisationId,
        int $inviterUserId,
        ?int $inviteeUserId,
        string $email,
        OrganisationRole $role,
        string $tokenHash,
        DateTimeImmutable $now,
        DateTimeImmutable $expiresAt,
    ): OrganisationInvitationSummary {
        return DB::transaction(function () use (
            $organisationId,
            $inviterUserId,
            $inviteeUserId,
            $email,
            $role,
            $tokenHash,
            $now,
            $expiresAt,
        ): OrganisationInvitationSummary {
            $unresolvedInvitation = OrganisationInvitation::query()
                ->where('organisation_id', $organisationId)
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->first(['id', 'expires_at']);

            if ($unresolvedInvitation !== null) {
                $unresolvedExpiresAt = $unresolvedInvitation->getAttribute('expires_at');

                if (! $unresolvedExpiresAt instanceof DateTimeInterface) {
                    throw new LogicException('The unresolved invitation has no expiry timestamp.');
                }

                if ($unresolvedExpiresAt->getTimestamp() > $now->getTimestamp()) {
                    throw new PendingInvitationExists;
                }

                $unresolvedInvitation->forceFill(['revoked_at' => $now])->save();
            }

            if ($inviteeUserId !== null) {
                $membership = OrganisationMembership::query()
                    ->where('organisation_id', $organisationId)
                    ->where('user_id', $inviteeUserId)
                    ->lockForUpdate()
                    ->first(['id', 'deactivated_at']);

                if ($membership !== null && $membership->getAttribute('deactivated_at') === null) {
                    throw new ActiveMemberCannotBeInvited;
                }
            }

            try {
                $invitation = OrganisationInvitation::query()->create([
                    'organisation_id' => $organisationId,
                    'email' => $email,
                    'role' => $role->value,
                    'token_hash' => $tokenHash,
                    'invited_by_user_id' => $inviterUserId,
                    'expires_at' => $expiresAt,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                if ($exception->index === self::UNRESOLVED_UNIQUE_CONSTRAINT) {
                    throw new PendingInvitationExists($exception);
                }

                throw $exception;
            }

            return self::toSummary($invitation);
        });
    }

    private static function toSummary(OrganisationInvitation $invitation): OrganisationInvitationSummary
    {
        $expiresAt = $invitation->getAttribute('expires_at');
        $createdAt = $invitation->getAttribute('created_at');

        if (! $expiresAt instanceof DateTimeInterface || ! $createdAt instanceof DateTimeInterface) {
            throw new LogicException('The invitation has invalid timestamps.');
        }

        return new OrganisationInvitationSummary(
            id: (int) $invitation->getKey(),
            email: (string) $invitation->getAttribute('email'),
            role: OrganisationRole::from((string) $invitation->getAttribute('role')),
            expiresAt: DateTimeImmutable::createFromInterface($expiresAt),
            acceptedAt: null,
            revokedAt: null,
            createdAt: DateTimeImmutable::createFromInterface($createdAt),
        );
    }
}
