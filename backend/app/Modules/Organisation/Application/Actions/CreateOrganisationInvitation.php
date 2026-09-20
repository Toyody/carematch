<?php

namespace App\Modules\Organisation\Application\Actions;

use App\Modules\Identity\Application\Contracts\CanonicalEmailNormalizer;
use App\Modules\Identity\Application\Contracts\IdentityUserLookup;
use App\Modules\Organisation\Application\Contracts\OrganisationInvitationCreator;
use App\Modules\Organisation\Application\Contracts\OrganisationInvitationNotifier;
use App\Modules\Organisation\Application\Data\OrganisationInvitationSummary;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\TenantContext;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class CreateOrganisationInvitation
{
    public function __construct(
        private CanonicalEmailNormalizer $emailNormalizer,
        private IdentityUserLookup $users,
        private OrganisationInvitationCreator $invitations,
        private OrganisationInvitationNotifier $notifier,
        private int $expiryDays,
    ) {
        if ($expiryDays < 1) {
            throw new InvalidArgumentException('Invitation expiry must be at least one day.');
        }
    }

    public function handle(
        TenantContext $tenant,
        string $email,
        OrganisationRole $role,
    ): OrganisationInvitationSummary {
        $canonicalEmail = $this->emailNormalizer->normalize($email);
        $existingUser = $this->users->findByCanonicalEmail($canonicalEmail);
        $rawToken = bin2hex(random_bytes(32));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $expiresAt = $now->add(new DateInterval("P{$this->expiryDays}D"));

        $invitation = $this->invitations->create(
            organisationId: $tenant->organisationId,
            inviterUserId: $tenant->userId,
            inviteeUserId: $existingUser?->id,
            email: $canonicalEmail,
            role: $role,
            tokenHash: hash('sha256', $rawToken),
            now: $now,
            expiresAt: $expiresAt,
        );

        $this->notifier->send($canonicalEmail, $rawToken);

        return $invitation;
    }
}
