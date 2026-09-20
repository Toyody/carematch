<?php

namespace App\Modules\Organisation\Infrastructure\Notifications;

use App\Modules\Organisation\Application\Contracts\OrganisationInvitationNotifier;
use Illuminate\Support\Facades\Notification;
use LogicException;

final class LaravelOrganisationInvitationNotifier implements OrganisationInvitationNotifier
{
    public function send(string $email, string $rawToken): void
    {
        $frontendUrl = config('carematch.frontend_url');

        if (! is_string($frontendUrl) || $frontendUrl === '') {
            throw new LogicException('The CareMatch frontend URL is not configured.');
        }

        $fragment = http_build_query(['token' => $rawToken], '', '&', PHP_QUERY_RFC3986);
        $acceptanceUrl = rtrim($frontendUrl, '/').'/invitations/accept#'.$fragment;

        Notification::route('mail', $email)->notify(
            new OrganisationInvitationNotification($acceptanceUrl),
        );
    }
}
