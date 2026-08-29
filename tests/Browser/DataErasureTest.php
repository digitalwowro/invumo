<?php

use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

/** @return array{User, Company} */
function dataErasureBrowserCompany(string $language = 'en'): array
{
    $owner = User::factory()->create([
        'name' => 'Data Lifecycle Owner',
        'email' => "data-lifecycle-{$language}@example.com",
        'language_code' => $language,
    ]);
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);
    $company = app(CreateCompany::class)->handle(
        $account,
        $owner,
        'Erase Browser SRL',
    );

    return [$owner, $company];
}

function openDataErasureBrowser(User $user, bool $mobile = false): mixed
{
    $page = visit('/login')->on();
    $page = $mobile ? $page->iPhone15() : $page->desktop();

    return $page
        ->type('Email address', $user->email)
        ->type('Password', 'password')
        ->click('Log in');
}

it('requires the strongest confirmation before erasing a Company', function () {
    [$owner, $company] = dataErasureBrowserCompany();

    $page = openDataErasureBrowser($owner);
    $page
        ->navigate(route('password.confirm', absolute: false))
        ->type('Password', 'password')
        ->click('@confirm-password-button');
    $page->navigate(route('company-data-lifecycle.show', $company, false))
        ->assertSee('This permanently erases the Company');
    $page->click('@destructive-action-trigger')
        ->assertSee('Enter Erase Browser SRL exactly.');
    $page->type('Workspace name', 'Erase Browser SRL')
        ->click('I understand that this permanently erases the Company and all tenant data.');
    $page->assertScript("document.querySelector('[data-testid=destructive-action-confirm]')?.disabled === false")
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('shows the account-erasure ownership guard on Romanian mobile', function () {
    [$owner] = dataErasureBrowserCompany('ro');

    openDataErasureBrowser($owner, mobile: true)
        ->navigate(route('profile.edit', absolute: false))
        ->assertSee('Ștergerea contului este blocată')
        ->assertSee('Transferă sau șterge definitiv')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
