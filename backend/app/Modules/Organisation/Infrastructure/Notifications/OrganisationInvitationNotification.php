<?php

namespace App\Modules\Organisation\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrganisationInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $acceptanceUrl,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been invited to CareMatch')
            ->line('You have been invited to join an organisation in CareMatch.')
            ->action('Accept invitation', $this->acceptanceUrl)
            ->line('This invitation expires after the configured invitation period.');
    }
}
