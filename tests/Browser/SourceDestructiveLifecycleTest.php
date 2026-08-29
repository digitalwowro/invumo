<?php

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\BankAccount;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

/** @return array{User, Company, Customer} */
function sourceLifecycleBrowserCompany(): array
{
    $owner = User::factory()->create([
        'name' => 'Source Lifecycle Owner',
        'email' => 'source-lifecycle@example.com',
    ]);
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);
    $company = app(CreateCompany::class)->handle($account, $owner, 'Lifecycle Browser SRL');
    $customer = app(TenantContext::class)->runAsSystem(
        $company->id,
        function (): Customer {
            $customer = Customer::query()->create([
                'type' => 'COMPANY', 'legal_name' => 'Disposable Customer SRL',
            ]);
            ProductService::query()->create(['name' => 'Disposable Service']);
            TaxPreset::query()->create([
                'name' => 'Disposable VAT', 'percentage' => '19', 'archived_at' => now(),
            ]);
            BankAccount::query()->create([
                'label' => 'Disposable Bank', 'bank_name' => 'Example Bank',
                'account_holder' => 'Lifecycle Browser SRL',
                'account_number' => 'RO49AAAA1B31007593840000',
                'swift_bic' => null, 'archived_at' => now(),
            ]);

            return $customer;
        },
    );

    return [$owner, $company, $customer];
}

function openSourceLifecyclePage(User $owner): mixed
{
    return visit('/login')->on()->desktop()
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in');
}

it('deletes an unreferenced Customer on desktop', function () {
    [$owner, $company, $customer] = sourceLifecycleBrowserCompany();

    openSourceLifecyclePage($owner)
        ->navigate(route('customers.show', [$company, $customer], false))
        ->click('Delete permanently')
        ->assertSee('Permanently delete this customer?')
        ->click('Confirm permanent deletion')
        ->assertSee('Customer permanently deleted.')
        ->assertNoJavaScriptErrors();
});

it('deletes an unreferenced Product or Service on desktop', function () {
    [$owner, $company] = sourceLifecycleBrowserCompany();

    openSourceLifecyclePage($owner)
        ->navigate(route('catalog.index', $company, false))
        ->click('Delete permanently')
        ->assertSee('Delete this entry permanently?')
        ->click('Delete entry permanently')
        ->assertSee('Product or Service deleted.')
        ->assertNoJavaScriptErrors();
});

it('restores and deletes an unreferenced tax preset on desktop', function () {
    [$owner, $company] = sourceLifecycleBrowserCompany();

    openSourceLifecyclePage($owner)
        ->navigate(route('company-tax-presets.index', $company, false))
        ->click('Restore')
        ->assertSee('Restore tax preset?')
        ->click('Restore tax preset')
        ->assertSee('Tax preset restored.')
        ->click('Delete permanently')
        ->click('Delete tax preset permanently')
        ->assertSee('Tax preset deleted.')
        ->assertNoJavaScriptErrors();
});

it('restores and deletes an unreferenced bank account on desktop', function () {
    [$owner, $company] = sourceLifecycleBrowserCompany();

    openSourceLifecyclePage($owner)
        ->navigate(route('company-bank-accounts.index', $company, false))
        ->click('Restore')
        ->assertSee('Restore bank account?')
        ->click('Restore bank account')
        ->assertSee('Bank account restored.')
        ->click('Delete permanently')
        ->click('Delete bank account permanently')
        ->assertSee('Bank account deleted.')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors();
});
