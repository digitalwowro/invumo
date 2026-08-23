<?php

namespace App\Modules\Identity\Notifications;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Password;
use LogicException;

final class ResetPasswordNotification extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    private readonly DateTimeInterface $retryUntilAt;

    public function __construct(#[\SensitiveParameter] string $token)
    {
        parent::__construct($token);
        $this->retryUntilAt = Carbon::now()->addMinutes($this->expiryMinutes());
        $this->afterCommit();
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $user = $notifiable instanceof User ? $notifiable->fresh() : null;

        return $channel === 'mail'
            && $user !== null
            && Password::broker()->tokenExists($user, $this->token);
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $user = $this->user($notifiable);
        $locale = $user->language_code;
        $expiry = $this->expiryMinutes();

        return (new MailMessage)
            ->subject(Lang::get('account_mail.recovery.subject', locale: $locale))
            ->greeting(Lang::get('account_mail.greeting', ['name' => $user->name], $locale))
            ->line(Lang::get('account_mail.recovery.introduction', locale: $locale))
            ->action(
                Lang::get('account_mail.recovery.action', locale: $locale),
                $this->resetUrl($user),
            )
            ->line(Lang::get('account_mail.recovery.expiry', ['minutes' => $expiry], $locale))
            ->line(Lang::get('account_mail.recovery.ignore', locale: $locale));
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
            throw new LogicException('Password-recovery recipient must be an Invumo User.');
        }

        return $notifiable;
    }

    private function expiryMinutes(): int
    {
        return (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
    }
}
