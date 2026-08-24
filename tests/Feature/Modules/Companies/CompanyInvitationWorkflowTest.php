<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Jobs\Middleware\RunTenantJob;
use App\Foundation\Jobs\TenantJob;
use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\AcceptCompanyInvitation;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Actions\InviteCompanyMember;
use App\Modules\Companies\Actions\ResendCompanyInvitation;
use App\Modules\Companies\Actions\RevokeCompanyInvitation;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Data\IssuedCompanyInvitation;
use App\Modules\Companies\Exceptions\CompanyInvitationException;
use App\Modules\Companies\Jobs\SendCompanyInvitation;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Notifications\CompanyInvitationNotification;
use App\Modules\Companies\Support\CompanyInvitationToken;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CompanyInvitationWorkflowTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_invitation_is_hashed_audited_queued_and_expires_after_seven_days(): void
    {
        Queue::fake();
        Notification::fake();
        Carbon::setTestNow('2026-08-23 10:00:00 Europe/Bucharest');
        $owner = $this->accountOwner(language: 'ro');
        $company = $this->companyFor($owner);

        $issued = app(InviteCompanyMember::class)->handle(
            $company,
            $owner,
            '  New.Member@Example.com ',
            CompanyRole::Member,
        );

        $invitation = $issued->invitation;
        $this->assertSame('New.Member@Example.com', $invitation->invited_email);
        $this->assertSame('new.member@example.com', $invitation->invited_email_normalized);
        $this->assertSame(CompanyInvitationToken::hash($issued->plainTextToken), $invitation->token_hash);
        $this->assertNotSame($issued->plainTextToken, $invitation->token_hash);
        $this->assertTrue($invitation->expires_at->equalTo(now()->addDays(7)));

        Queue::assertPushed(
            SendCompanyInvitation::class,
            function (SendCompanyInvitation $job) use ($company, $invitation): bool {
                $this->assertInstanceOf(ShouldQueue::class, $job);
                $this->assertInstanceOf(ShouldBeEncrypted::class, $job);
                $this->assertSame($company->id, $job->identity->companyId);
                $this->assertSame($invitation->id, $job->invitationId);
                $this->assertSame('ro', $job->locale);
                $this->assertSame([60, 300, 900, 3600, 21600], $job->backoff());
                $this->assertSame(6, $job->tries);

                return true;
            },
        );

        $this->runJob($this->jobFor($issued, $owner));

        Notification::assertSentOnDemand(
            CompanyInvitationNotification::class,
            function ($notification, $channels, AnonymousNotifiable $notifiable, $locale): bool {
                $this->assertSame(['mail'], $channels);
                $this->assertSame('New.Member@Example.com', $notifiable->routeNotificationFor('mail'));

                return $locale === 'ro';
            },
        );

        app(TenantContext::class)->runAsSystem($company->id, function () use ($invitation): void {
            $event = AuditEvent::query()
                ->where('action', 'company.invitation.created')
                ->where('target_id', $invitation->id)
                ->firstOrFail();

            $this->assertSame(['role', 'expires_at'], array_keys($event->after));
            $this->assertStringNotContainsString('example.com', json_encode($event->after, JSON_THROW_ON_ERROR));
        });
    }

    public function test_duplicate_pending_and_existing_member_invitations_are_rejected(): void
    {
        Queue::fake();
        Notification::fake();
        $owner = $this->accountOwner();
        $existing = $this->accountOwner(email: 'existing@example.com');
        $company = $this->companyFor($owner);
        $company->memberships()->create([
            'user_id' => $existing->id,
            'role' => CompanyRole::Member,
        ]);

        try {
            app(InviteCompanyMember::class)->handle(
                $company,
                $owner,
                'existing@example.com',
                CompanyRole::Member,
            );
            $this->fail('An existing member should not be invited.');
        } catch (CompanyInvitationException $exception) {
            $this->assertSame('already_member', $exception->reason());
        }

        app(InviteCompanyMember::class)->handle(
            $company,
            $owner,
            'pending@example.com',
            CompanyRole::Admin,
        );

        $this->expectExceptionObject(CompanyInvitationException::alreadyPending());
        app(InviteCompanyMember::class)->handle(
            $company,
            $owner,
            'PENDING@example.com',
            CompanyRole::Member,
        );
    }

    public function test_resend_rotates_the_token_and_revoke_invalidates_delivery_and_acceptance(): void
    {
        Queue::fake();
        Notification::fake();
        Carbon::setTestNow('2026-08-23 10:00:00 Europe/Bucharest');
        $owner = $this->accountOwner();
        $company = $this->companyFor($owner);
        $first = app(InviteCompanyMember::class)->handle(
            $company,
            $owner,
            'invited@example.com',
            CompanyRole::Member,
        );

        Carbon::setTestNow(now()->addDay());
        $second = app(ResendCompanyInvitation::class)->handle(
            $company,
            $owner,
            $first->invitation,
        );

        $this->assertNotSame($first->plainTextToken, $second->plainTextToken);
        $this->assertSame(
            CompanyInvitationToken::hash($second->plainTextToken),
            $second->invitation->token_hash,
        );
        $this->assertTrue($second->invitation->expires_at->equalTo(now()->addDays(7)));
        $this->runJob($this->jobFor($first, $owner));
        Notification::assertNothingSent();

        app(RevokeCompanyInvitation::class)->handle($company, $owner, $second->invitation);
        $this->runJob($this->jobFor($second, $owner));
        Notification::assertNothingSent();

        $invitee = $this->accountOwner(email: 'invited@example.com');
        $this->expectExceptionObject(CompanyInvitationException::unavailable());
        app(AcceptCompanyInvitation::class)->handle($invitee, $second->plainTextToken);
    }

    public function test_acceptance_matches_email_creates_one_membership_and_is_single_use(): void
    {
        Queue::fake();
        Notification::fake();
        $owner = $this->accountOwner();
        $invitee = $this->accountOwner(email: 'invitee@example.com');
        $company = $this->companyFor($owner);
        $issued = app(InviteCompanyMember::class)->handle(
            $company,
            $owner,
            $invitee->email,
            CompanyRole::Admin,
        );

        $acceptedCompany = app(AcceptCompanyInvitation::class)
            ->handle($invitee, $issued->plainTextToken);

        $this->assertTrue($company->is($acceptedCompany));
        $this->assertDatabaseHas('company_memberships', [
            'company_id' => $company->id,
            'user_id' => $invitee->id,
            'role' => CompanyRole::Admin->value,
        ]);
        $this->assertDatabaseHas('company_invitations', [
            'id' => $issued->invitation->id,
            'accepted_by_user_id' => $invitee->id,
        ]);

        $this->expectExceptionObject(CompanyInvitationException::unavailable());
        app(AcceptCompanyInvitation::class)->handle($invitee, $issued->plainTextToken);
    }

    public function test_wrong_email_and_expired_invitation_cannot_be_accepted(): void
    {
        Queue::fake();
        Notification::fake();
        Carbon::setTestNow('2026-08-23 10:00:00 Europe/Bucharest');
        $owner = $this->accountOwner();
        $wrongUser = $this->accountOwner(email: 'wrong@example.com');
        $company = $this->companyFor($owner);
        $issued = app(InviteCompanyMember::class)->handle(
            $company,
            $owner,
            'right@example.com',
            CompanyRole::Member,
        );

        try {
            app(AcceptCompanyInvitation::class)->handle($wrongUser, $issued->plainTextToken);
            $this->fail('A different email address should not accept the invitation.');
        } catch (CompanyInvitationException $exception) {
            $this->assertSame('email_mismatch', $exception->reason());
        }

        Carbon::setTestNow(now()->addDays(7));
        $rightUser = $this->accountOwner(email: 'right@example.com');
        $this->expectExceptionObject(CompanyInvitationException::unavailable());
        app(AcceptCompanyInvitation::class)->handle($rightUser, $issued->plainTextToken);
    }

    private function jobFor(IssuedCompanyInvitation $issued, User $actor): SendCompanyInvitation
    {
        return new SendCompanyInvitation(
            companyId: $issued->invitation->company_id,
            invitationId: $issued->invitation->id,
            plainTextToken: $issued->plainTextToken,
            locale: $actor->language_code,
        );
    }

    private function runJob(SendCompanyInvitation $job): void
    {
        app(RunTenantJob::class)->handle(
            $job,
            function (TenantJob $queued): void {
                app()->call([$queued, 'handle']);
            },
        );
    }

    private function accountOwner(string $email = 'owner@example.com', string $language = 'en'): User
    {
        $user = User::factory()->create(['email' => $email, 'language_code' => $language]);
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        Account::query()->create(['owner_user_id' => $user->id, 'plan_id' => $plan->id]);

        return $user;
    }

    private function companyFor(User $owner): Company
    {
        return app(CreateCompany::class)->handle(
            $owner->account()->firstOrFail(),
            $owner,
            'Acme SRL',
        );
    }
}
