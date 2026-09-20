<?php

namespace App\Modules\Organisation\Application\Contracts;

interface OrganisationInvitationNotifier
{
    public function send(string $email, string $rawToken): void;
}
