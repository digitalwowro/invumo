<?php

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerDeliveryRecipient;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Support\Str;

function companyForInvoiceBrowser(string $language = 'en'): array
{
    $owner = User::factory()->create([
        'name' => 'Invoice Owner',
        'email' => 'invoice-'.$language.'-'.Str::lower(Str::random(8)).'@example.com',
        'language_code' => $language,
    ]);
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);
    $company = app(CreateCompany::class)->handle($account, $owner, 'Invoice Browser SRL');
    app(TenantContext::class)->runAsSystem($company->id, function () use ($language): void {
        CompanySetting::query()->firstOrFail()->update([
            'timezone' => 'Europe/Bucharest',
            'default_document_language' => $language,
            'default_payment_term_days' => 30,
        ]);
        $currency = CompanyCurrency::query()->create([
            'currency_code' => 'RON', 'currency_precision' => 2,
            'is_default' => true, 'active' => true,
        ]);
        $tax = TaxPreset::query()->create([
            'name' => 'TVA', 'percentage' => '19', 'is_default' => true,
        ]);
        TaxPreset::query()->create([
            'name' => 'Reduced VAT', 'percentage' => '9', 'is_default' => false,
        ]);
        ProductService::query()->create([
            'name' => 'Browser invoice consulting',
            'description' => 'Implementation and support',
            'internal_code' => 'INV-CONSULT',
            'unit_price' => '100.00000000',
            'currency_id' => $currency->id,
            'unit' => 'hour',
            'period_unit' => 'NONE',
            'tax_preset_id' => $tax->id,
        ]);
        $customer = Customer::query()->create([
            'type' => 'COMPANY',
            'legal_name' => 'Browser Invoice Customer SRL',
            'email' => 'invoice-customer@example.com',
            'document_language' => $language,
        ]);
        CustomerDeliveryRecipient::query()->create([
            'customer_id' => $customer->id,
            'role' => 'TO',
            'explicit_name' => 'Invoice recipient',
            'explicit_email' => 'invoice-customer@example.com',
            'display_order' => 1,
        ]);
    });

    return [$owner, $company];
}

function openInvoiceCreate(User $owner, Company $company, bool $mobile = false): mixed
{
    $page = visit('/login')->on();
    $page = $mobile ? $page->iPhone15() : $page->desktop();

    return $page
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('invoices.index', $company, false));
}
