<?php

namespace App\Modules\Companies\Notifications;

use App\Modules\Companies\Models\CompanyInvitation;
use App\Modules\Companies\Support\CompanyInvitationToken;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CompanyInvitationNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    public function __construct(
        private readonly string $invitationId,
        private readonly string $plainTextToken,
        private readonly string $companyName,
        private readonly string $inviterName,
        private readonly CarbonInterface $expiresAt,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $invitation = CompanyInvitation::query()->find($this->invitationId);

        return $channel === 'mail'
            && $invitation !== null
            && $invitation->revoked_at === null
            && $invitation->accepted_at === null
            && $invitation->expires_at->isAfter(now())
            && hash_equals(
                $invitation->token_hash,
                CompanyInvitationToken::hash($this->plainTextToken),
            );
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

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function retryUntil(): DateTimeInterface
    {
        return $this->expiresAt;
    }
}
