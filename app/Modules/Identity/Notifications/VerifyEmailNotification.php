<?php

namespace App\Modules\Identity\Notifications;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;

final class VerifyEmailNotification extends VerifyEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    private readonly DateTimeInterface $retryUntilAt;

    public function __construct()
    {
        $this->retryUntilAt = Carbon::now()
            ->addMinutes((int) config('auth.verification.expire', 60));
        $this->afterCommit();
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $user = $notifiable instanceof User ? $notifiable->fresh() : null;

        return $channel === 'mail'
            && $user !== null
            && ! $user->hasVerifiedEmail();
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $user = $this->user($notifiable);
        $locale = $user->language_code;

        return (new MailMessage)
            ->subject(Lang::get('account_mail.verification.subject', locale: $locale))
            ->greeting(Lang::get('account_mail.greeting', ['name' => $user->name], $locale))
            ->line(Lang::get('account_mail.verification.introduction', locale: $locale))
            ->action(
                Lang::get('account_mail.verification.action', locale: $locale),
                $this->verificationUrl($user),
            )
            ->line(Lang::get('account_mail.verification.ignore', locale: $locale));
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function retryUntil(): DateTimeInterface
    {
        return $this->retryUntilAt;
    }

    private function user(mixed $notifiable): User
    {
        if (! $notifiable instanceof User) {
            throw new \LogicException('Verification email recipient must be an Invumo User.');
        }

        return $notifiable;
    }
}
