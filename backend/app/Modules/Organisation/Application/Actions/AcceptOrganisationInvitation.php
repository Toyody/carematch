<?php

namespace App\Modules\Organisation\Application\Actions;

use App\Modules\Identity\Application\Contracts\CanonicalEmailNormalizer;
use App\Modules\Identity\Application\Contracts\IdentityUserLookup;
use App\Modules\Organisation\Application\Contracts\OrganisationInvitationAcceptor;
use App\Modules\Organisation\Application\Exceptions\InvitationUnavailable;
use DateTimeImmutable;
use DateTimeZone;

final readonly class AcceptOrganisationInvitation
{
    public function __construct(
        private IdentityUserLookup $users,
        private CanonicalEmailNormalizer $emailNormalizer,
        private OrganisationInvitationAcceptor $invitations,
    ) {}

    public function handle(int $userId, string $rawToken): void
    {
        $user = $this->users->findById($userId);

        if ($user === null) {
            throw new InvitationUnavailable;
        }

        $this->invitations->accept(
            tokenHash: hash('sha256', $rawToken),
            userId: $user->id,
            canonicalEmail: $this->emailNormalizer->normalize($user->email),
            acceptedAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
    }
}
