<?php

namespace Tests\Feature\Modules\Companies;

use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Actions\InviteCompanyMember;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyInvitationView;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyInvitationHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_owner_and_admin_can_manage_invitations_but_member_cannot(): void
    {
        Notification::fake();
        $owner = $this->accountOwner();
        $admin = $this->accountOwner(email: 'admin@example.com');
        $member = $this->accountOwner(email: 'member@example.com');
        $company = $this->companyFor($owner);
        $company->memberships()->create(['user_id' => $admin->id, 'role' => CompanyRole::Admin]);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);

        $this->actingAs($owner)
            ->get(route('company-members.index', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('companies/members/index')
                ->where('company.id', $company->id)
                ->has('members', 3)
                ->has('invitations', 0));

        $this->actingAs($admin)
            ->post(route('company-invitations.store', $company), [
                'email' => 'new@example.com',
                'role' => CompanyRole::Member->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->actingAs($member)
            ->get(route('company-members.index', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManageMembers', false)
                ->has('members', 3)
                ->has('invitations', 0));
        $this->post(route('company-invitations.store', $company), [
            'email' => 'blocked@example.com',
            'role' => CompanyRole::Admin->value,
        ])->assertForbidden();

        $this->assertDatabaseMissing('company_invitations', [
            'invited_email_normalized' => 'blocked@example.com',
        ]);
    }

    public function test_cross_company_management_does_not_reveal_company_existence(): void
    {
        $owner = $this->accountOwner();
        $outsider = $this->accountOwner(email: 'outsider@example.com');
        $company = $this->companyFor($owner);

        $this->actingAs($outsider)
            ->get(route('company-members.index', $company))
            ->assertNotFound();
    }

    public function test_public_review_supports_guests_existing_users_and_verified_acceptance(): void
    {
        Notification::fake();
        $owner = $this->accountOwner();
        $invitee = $this->accountOwner(email: 'invitee@example.com');
        $company = $this->companyFor($owner);
        $issued = app(InviteCompanyMember::class)->handle(
            $company,
            $owner,
            $invitee->email,
            CompanyRole::Member,
        );
        $reviewUrl = route('company-invitations.show', $issued->plainTextToken);

        $guestResponse = $this->get($reviewUrl)
            ->assertOk()
            ->assertSessionHas('url.intended', $reviewUrl)
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/company-invitation')
                ->where('invitation.available', true)
                ->where('invitation.authenticated', false)
                ->where('invitation.companyName', 'Acme SRL')
                ->where('invitation.invitedEmail', null)
                ->where('invitation.role', null)
                ->where('invitation.expiresAt', null));
        $guestResponse->assertDontSee('invitee@example.com', false);
        $guestResponse->assertDontSee(
            $issued->invitation->expires_at->toIso8601String(),
            false,
        );

        $this->post(route('company-invitations.accept', $issued->plainTextToken))
            ->assertRedirect(route('login'));

        $this->actingAs($invitee)
            ->get($reviewUrl)
            ->assertInertia(fn (Assert $page) => $page
                ->where('invitation.emailMatches', true)
                ->where('invitation.emailVerified', true)
                ->where('invitation.invitedEmail', 'invitee@example.com')
                ->where('invitation.role', CompanyRole::Member->value)
                ->where('invitation.expiresAt', fn (mixed $value) => is_string($value)));

        $this->post(route('company-invitations.accept', $issued->plainTextToken))
            ->assertRedirect(route('companies.dashboard', $company));

        $this->assertDatabaseHas('company_memberships', [
            'company_id' => $company->id,
            'user_id' => $invitee->id,
            'role' => CompanyRole::Member->value,
        ]);
    }

    public function test_review_blocks_wrong_or_unverified_accounts_and_explains_expiry(): void
    {
        Notification::fake();
        $owner = $this->accountOwner();
        $wrong = $this->accountOwner(email: 'wrong@example.com');
        $unverified = $this->accountOwner(email: 'target@example.com', verified: false);
        $company = $this->companyFor($owner);
        $issued = app(InviteCompanyMember::class)->handle(
            $company,
            $owner,
            $unverified->email,
            CompanyRole::Admin,
        );
        $url = route('company-invitations.show', $issued->plainTextToken);

        $wrongAccountResponse = $this->actingAs($wrong)
            ->get($url)
            ->assertInertia(fn (Assert $page) => $page
                ->where('invitation.emailMatches', false)
                ->where('invitation.invitedEmail', null)
                ->where('invitation.role', null)
                ->where('invitation.expiresAt', null));
        $wrongAccountResponse->assertDontSee('target@example.com', false);

        $this->actingAs($unverified)
            ->get($url)
            ->assertSessionHas('url.intended', $url)
            ->assertInertia(fn (Assert $page) => $page
                ->where('invitation.emailMatches', true)
                ->where('invitation.emailVerified', false));

        $issued->invitation->update(['expires_at' => now()->subSecond()]);

        $this->get($url)
            ->assertInertia(fn (Assert $page) => $page
                ->where('invitation.available', false)
                ->where('invitation.status', 'expired')
                ->where('invitation.invitedEmail', null)
                ->where('invitation.role', null)
                ->where('invitation.expiresAt', null));
    }

    public function test_review_uses_the_stored_normalized_email_for_identity_matching(): void
    {
        Notification::fake();
        $owner = $this->accountOwner();
        $company = $this->companyFor($owner);
        $issued = app(InviteCompanyMember::class)->handle(
            $company,
            $owner,
            'target@example.com',
            CompanyRole::Member,
        );
        $actor = new User;
        $actor->setRawAttributes([
            'email' => 'normalization-must-not-use-this@example.com',
            'email_normalized' => 'target@example.com',
            'email_verified_at' => now(),
        ]);

        $view = app(CompanyInvitationView::class)
            ->for($issued->plainTextToken, $actor);

        $this->assertTrue($view['emailMatches']);
        $this->assertSame('target@example.com', $view['invitedEmail']);
    }

    private function accountOwner(
        string $email = 'owner@example.com',
        bool $verified = true,
    ): User {
        $factory = User::factory();
        $user = ($verified ? $factory : $factory->unverified())->create(['email' => $email]);
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        Account::query()->create(['owner_user_id' => $user->id, 'plan_id' => $plan->id]);

        return $user->refresh();
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
