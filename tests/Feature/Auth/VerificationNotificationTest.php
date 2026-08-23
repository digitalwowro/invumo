<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Identity\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Tests\TestCase;

class VerificationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::emailVerification());
    }

    public function test_sends_verification_notification(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('home'));

        Notification::assertSentTo(
            $user,
            VerifyEmailNotification::class,
            function (VerifyEmailNotification $notification): bool {
                $this->assertInstanceOf(ShouldQueue::class, $notification);
                $this->assertInstanceOf(ShouldBeEncrypted::class, $notification);
                $this->assertSame('en', $notification->locale);

                return true;
            },
        );
    }

    public function test_does_not_send_verification_notification_if_email_is_verified(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect(route('home', absolute: false));

        Notification::assertNothingSent();
    }

    public function test_verification_email_is_localized_and_suppressed_after_verification(): void
    {
        $user = User::factory()->unverified()->create([
            'name' => 'Ana',
            'language_code' => 'ro',
        ]);
        $notification = new VerifyEmailNotification;
        $message = $notification->toMail($user);

        $this->assertSame('Verifică adresa de email pentru Invumo', $message->subject);
        $this->assertSame('Bună, Ana!', $message->greeting);
        $this->assertSame('Verifică adresa de email', $message->actionText);
        $this->assertTrue($notification->shouldSend($user, 'mail'));

        $user->markEmailAsVerified();

        $this->assertFalse($notification->shouldSend($user, 'mail'));
    }
}
