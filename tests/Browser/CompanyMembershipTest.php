<?php

use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

function membershipBrowserUser(string $name, string $language = 'en'): User
{
    $user = User::factory()->create([
        'name' => $name,
        'email' => Str::slug($name).'-'.Str::lower(Str::random(8)).'@example.com',
        'language_code' => $language,
    ]);
    Account::query()->create([
        'owner_user_id' => $user->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);

    return $user;
}

function membershipBrowserCompany(User $owner, User $member): Company
{
    $company = app(CreateCompany::class)->handle(
        $owner->account()->firstOrFail(),
        $owner,
        'Governance Browser SRL',
    );
    $company->memberships()->create([
        'user_id' => $member->id,
        'role' => CompanyRole::Member,
    ]);

    return $company;
}

function openCompanyMembers(User $user, Company $company, bool $mobile = false): mixed
{
    $page = visit('/login')->on();
    $page = $mobile ? $page->iPhone15() : $page->desktop();

    return $page
        ->type('Email address', $user->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('company-members.index', $company, false));
}

it('lets an Owner confirm role changes and member removal on desktop', function () {
    $owner = membershipBrowserUser('Governance Owner');
    $member = membershipBrowserUser('Governance Member');
    $company = membershipBrowserCompany($owner, $member);

    openCompanyMembers($owner, $company)
        ->assertSee('Members and invitations')
        ->assertSee('Governance Member')
        ->click('Change role')
        ->assertSee('Governance Member will become Admin. Their permissions change immediately.')
        ->click('Confirm role change')
        ->assertSee('Member role changed.')
        ->assertSee('Admin')
        ->click('Remove')
        ->assertSee('Governance Member will immediately lose access to this Company.')
        ->click('Remove member')
        ->assertSee('Member removed from the Company.')
        ->assertDontSee('Governance Member')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('transfers ownership after the shared recent-password confirmation', function () {
    $owner = membershipBrowserUser('Transfer Owner');
    $destination = membershipBrowserUser('Transfer Destination');
    $company = membershipBrowserCompany($owner, $destination);
    $destinationMembership = $company->memberships()
        ->where('user_id', $destination->id)
        ->firstOrFail();
    $page = openCompanyMembers($owner, $company);

    $page
        ->navigate(route('password.confirm', absolute: false))
        ->type('Password', 'password')
        ->click('@confirm-password-button')
        ->navigate(route('company-members.index', $company, false))
        ->click('@ownership-transfer-trigger')
        ->assertSee('Transfer Company ownership?')
        ->assertSee('Select an existing member')
        ->click('Select an existing member')
        ->click("@ownership-destination-{$destinationMembership->id}")
        ->click('@ownership-transfer-confirm')
        ->assertSee('Company ownership transferred.')
        ->assertDontSee('Transfer Company ownership?')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('lets a Romanian Member leave safely on a narrow viewport', function () {
    $owner = membershipBrowserUser('Proprietar Guvernanță');
    $member = membershipBrowserUser('Membru Guvernanță', 'ro');
    $company = membershipBrowserCompany($owner, $member);

    openCompanyMembers($member, $company, mobile: true)
        ->assertSee('Membri și invitații')
        ->assertSee('Părăsește această companie')
        ->assertDontSee('Invită un membru')
        ->assertDontSee('Transferă proprietatea')
        ->click('Părăsește compania')
        ->assertSee('Vei pierde imediat accesul.')
        ->click('Confirmă părăsirea')
        ->assertPathIs(route('companies.index', absolute: false))
        ->assertSee('Companiile tale')
        ->assertDontSee('Governance Browser SRL')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
