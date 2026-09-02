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

/** @return array{User, Company, Customer, ProductService} */
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
    [$customer, $product] = app(TenantContext::class)->runAsSystem(
        $company->id,
        function (): array {
            $customer = Customer::query()->create([
                'type' => 'COMPANY', 'legal_name' => 'Disposable Customer SRL',
            ]);
            $product = ProductService::query()->create(['name' => 'Disposable Service']);
            TaxPreset::query()->create([
                'name' => 'Disposable VAT', 'percentage' => '19', 'archived_at' => now(),
            ]);
            BankAccount::query()->create([
                'label' => 'Disposable Bank', 'bank_name' => 'Example Bank',
                'account_holder' => 'Lifecycle Browser SRL',
                'account_number' => 'RO49AAAA1B31007593840000',
                'swift_bic' => null, 'archived_at' => now(),
            ]);

            return [$customer, $product];
        },
    );

    return [$owner, $company, $customer, $product];
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
        ->click('Delete')
        ->assertSee('Permanently delete this customer?')
        ->click('@guarded-action-confirm')
        ->assertSee('Customer permanently deleted.')
        ->assertNoJavaScriptErrors();
});

it('deletes an unreferenced Product or Service on desktop', function () {
    [$owner, $company, , $product] = sourceLifecycleBrowserCompany();

    openSourceLifecyclePage($owner)
        ->navigate(route('catalog.show', [$company, $product], false))
        ->click('Delete')
        ->assertSee('Delete this entry permanently?')
        ->click('@guarded-action-confirm')
        ->assertSee('Product or Service deleted.')
        ->assertNoJavaScriptErrors();
});

it('restores and deletes an unreferenced tax preset on desktop', function () {
    [$owner, $company] = sourceLifecycleBrowserCompany();

    openSourceLifecyclePage($owner)
        ->navigate(route('company-tax-presets.index', $company, false))
        ->click('Restore')
        ->assertSee('Restore tax preset?')
        ->click('@confirmation-dialog-confirm')
        ->assertSee('Tax preset restored.')
        ->click('Delete')
        ->click('@guarded-action-confirm')
        ->assertSee('Tax preset deleted.')
        ->assertNoJavaScriptErrors();
});

it('restores and deletes an unreferenced bank account on desktop', function () {
    [$owner, $company] = sourceLifecycleBrowserCompany();

    openSourceLifecyclePage($owner)
        ->navigate(route('company-bank-accounts.index', $company, false))
        ->click('Restore')
        ->assertSee('Restore bank account?')
        ->click('@confirmation-dialog-confirm')
        ->assertSee('Bank account restored.')
        ->click('Delete')
        ->click('@guarded-action-confirm')
        ->assertSee('Bank account deleted.')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors();
});
