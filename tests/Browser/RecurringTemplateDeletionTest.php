<?php

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Recurring\Actions\SyncRecurringDispatch;
use App\Modules\Recurring\Models\RecurringTemplate;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

/** @return array{User, Company, RecurringTemplate} */
function recurringDeletionBrowserFixture(): array
{
    $owner = User::factory()->create([
        'name' => 'Recurring Deletion Owner',
        'email' => 'recurring-deletion@example.com',
    ]);
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);
    $company = app(CreateCompany::class)->handle($account, $owner, 'Recurring Delete SRL');
    $template = app(TenantContext::class)->runAsSystem(
        $company->id,
        function (): RecurringTemplate {
            $customer = Customer::query()->create([
                'type' => 'COMPANY', 'legal_name' => 'Browser Customer SRL',
            ]);
            $template = RecurringTemplate::query()->create([
                'client_creation_key' => (string) Str::uuid7(),
                'internal_name' => 'Automation without invoices',
                'customer_id' => $customer->id,
            ]);
            $template->update([
                'state' => 'ACTIVE', 'recurrence_kind' => 'MONTHLY',
                'start_date' => '2026-09-29', 'schedule_anchor_ordinal' => 0,
                'next_logical_ordinal' => 0, 'next_occurrence_date' => '2026-09-29',
                'schedule_timezone' => 'UTC', 'schedule_local_time' => '09:00',
                'next_run_at' => '2026-09-29 09:00:00+00', 'activated_at' => now(),
            ]);
            app(SyncRecurringDispatch::class)->handle($template);

            return $template;
        },
    );

    return [$owner, $company, $template];
}

it('warns before permanently deleting active recurring automation', function () {
    [$owner, $company, $template] = recurringDeletionBrowserFixture();

    visit('/login')->on()->desktop()
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('recurring.edit', [$company, $template], false))
        ->click('Delete')
        ->assertSee('This template has left Draft state.')
        ->click('Permanently delete')
        ->assertSee('Recurring template permanently deleted.')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors();
});
