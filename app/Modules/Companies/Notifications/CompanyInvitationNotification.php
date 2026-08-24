<?php

namespace App\Modules\Companies\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CompanyInvitationNotification extends Notification
{
    public function __construct(
        private readonly string $plainTextToken,
        private readonly string $companyName,
        private readonly string $inviterName,
        private readonly CarbonInterface $expiresAt,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $localizedExpiry = $this->expiresAt->copy();
        $localizedExpiry->setLocale(app()->getLocale());

        return (new MailMessage)
            ->subject(__('company_invitations_mail.subject', ['company' => $this->companyName]))
            ->greeting(__('company_invitations_mail.greeting'))
            ->line(__('company_invitations_mail.introduction', [
                'inviter' => $this->inviterName,
                'company' => $this->companyName,
            ]))
            ->action(
                __('company_invitations_mail.action'),
                route('company-invitations.show', $this->plainTextToken),
            )
            ->line(__('company_invitations_mail.expiry', [
                'date' => $localizedExpiry->isoFormat('LLL'),
            ]))
            ->line(__('company_invitations_mail.ignore'));
    }
}
