<?php

namespace App\Modules\Compliance\Infrastructure\Notifications;

use App\Modules\Compliance\Application\Data\ExpiryDigestSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ComplianceExpiryDigestNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ExpiryDigestSummary $summary,
        private readonly string $complianceUrl,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('CareMatch credential expiry digest')
            ->line(sprintf('Expired credentials: %d', $this->summary->expiredCount))
            ->line(sprintf('Credentials expiring within %d days: %d', $this->summary->warningDays, $this->summary->expiringCount))
            ->action('Review compliance details', $this->complianceUrl)
            ->line('This operational digest does not constitute legal or regulatory compliance advice.');
    }
}
