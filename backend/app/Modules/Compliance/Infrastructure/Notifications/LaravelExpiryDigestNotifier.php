<?php

namespace App\Modules\Compliance\Infrastructure\Notifications;

use App\Modules\Compliance\Application\Contracts\ExpiryDigestNotifier;
use App\Modules\Compliance\Application\Data\ExpiryDigestSummary;
use App\Modules\Compliance\Application\Exceptions\PermanentExpiryDigestFailure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use LogicException;

final class LaravelExpiryDigestNotifier implements ExpiryDigestNotifier
{
    public function send(int $organisationId, int $requestedByUserId, ExpiryDigestSummary $summary): void
    {
        $recipient = DB::table('users')
            ->join('organisation_memberships as memberships', function ($join) use ($organisationId): void {
                $join->on('memberships.user_id', '=', 'users.id')
                    ->where('memberships.organisation_id', $organisationId)
                    ->where('memberships.role', 'admin')
                    ->whereNull('memberships.deactivated_at');
            })
            ->where('users.id', $requestedByUserId)
            ->value('users.email');

        if (! is_string($recipient) || $recipient === '') {
            throw new PermanentExpiryDigestFailure('recipient_no_longer_eligible');
        }

        $frontendUrl = config('carematch.frontend_url');
        if (! is_string($frontendUrl) || $frontendUrl === '') {
            throw new LogicException('The CareMatch frontend URL is not configured.');
        }

        Notification::route('mail', $recipient)->notify(new ComplianceExpiryDigestNotification(
            $summary,
            rtrim($frontendUrl, '/').sprintf('/organisations/%d/qualifications', $organisationId),
        ));
    }
}
