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
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Quotes\Actions\CreateQuoteDraft;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

afterEach(function (): void {
    Company::query()->pluck('id')->each(function (string $companyId): void {
        app(TenantContext::class)->runAsSystem(
            $companyId,
            fn () => Quote::query()->where('lifecycle', '!=', QuoteLifecycle::Draft)
                ->update(['lifecycle' => QuoteLifecycle::Draft]),
        );
    });
});

function companyForQuoteBrowser(string $language = 'en'): array
{
    $owner = User::factory()->create([
        'name' => 'Quote Owner',
        'email' => "quote-{$language}@example.com",
        'language_code' => $language,
    ]);
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);
    $company = app(CreateCompany::class)->handle($account, $owner, 'Quote Browser SRL');
    app(TenantContext::class)->runAsSystem($company->id, function () use ($language): void {
        CompanySetting::query()->firstOrFail()->update([
            'timezone' => 'Europe/Bucharest',
            'default_document_language' => $language,
        ]);
        $currency = CompanyCurrency::query()->create([
            'currency_code' => 'RON', 'currency_precision' => 2,
            'is_default' => true, 'active' => true,
        ]);
        $tax = TaxPreset::query()->create([
            'name' => 'TVA', 'percentage' => '19', 'is_default' => true,
        ]);
        Customer::query()->create([
            'type' => 'COMPANY', 'legal_name' => 'Browser Customer SRL',
            'document_language' => $language,
        ]);
        ProductService::query()->create([
            'name' => 'Browser Consulting', 'unit_price' => '100',
            'currency_id' => $currency->id, 'unit' => 'hour',
            'period_unit' => 'NONE', 'tax_preset_id' => $tax->id,
        ]);
    });

    return [$owner, $company];
}

function openQuoteCreate(User $owner, Company $company, bool $mobile = false): mixed
{
    $page = visit('/login')->on();
    $page = $mobile ? $page->iPhone15() : $page->desktop();

    return $page
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('quotes.index', $company, false));
}

function acceptedQuoteForBrowser(Company $company, User $owner): Document
{
    $document = app(CreateQuoteDraft::class)->handle($company, $owner, (string) Str::uuid7());

    return app(TenantContext::class)->runAsSystem($company->id, function () use ($document): Document {
        $document->update([
            'customer_reference' => 'PO-CONVERT-42',
            'subtotal' => '100', 'tax_total' => '0', 'total' => '100',
        ]);
        Quote::query()->whereKey($document->id)->update(['lifecycle' => QuoteLifecycle::Accepted]);
        DocumentLine::query()->create([
            'document_id' => $document->id, 'position' => 1,
            'description' => 'Converted consulting', 'item_price' => '100',
            'quantity' => '1', 'unit' => 'hour', 'period_unit' => 'NONE',
            'discount_percentage' => '0', 'discount_amount' => '0',
            'tax_percentage' => '0', 'items_subtotal' => '100',
            'items_total' => '100', 'grand_subtotal' => '100',
            'tax_amount' => '0', 'final_line_total' => '100',
        ]);

        return $document->refresh();
    });
}

it('creates and calculates a manual Quote Draft without viewport overflow', function () {
    [$owner, $company] = companyForQuoteBrowser();

    openQuoteCreate($owner, $company)
        ->click('New quote')
        ->assertSee('New quote')
        ->click('@document-customer-select')
        ->type('Customer search', 'Browser Customer')
        ->click('@document-customer-search')
        ->click('@document-customer-result')
        ->assertSee('Review the Customer defaults')
        ->click('@document-customer-confirm')
        ->assertSee('Browser Customer SRL')
        ->click('Add product or service')
        ->click('@document-product-select-0')
        ->type('Product or Service search', 'Browser Consulting')
        ->click('@document-product-search')
        ->click('@document-product-result')
        ->click('@document-product-confirm')
        ->assertValue('Product or Service', 'Browser Consulting')
        ->assertValue('Description', '')
        ->type('Description', 'Browser Consulting')
        ->type('Quantity', '2')
        ->type('Discount %', '10')
        ->assertSee('214.20')
        ->type('Reference / PO', 'PO-BROWSER-42')
        ->type('@quote-validity-days', '45')
        ->type('Item price', '100')
        ->click('Save quote')
        ->assertSee('Quote saved.')
        ->assertSee('Q-'.now('Europe/Bucharest')->year.'-0001')
        ->type('Description', 'Unsaved Quote sentinel')
        ->click('@document-customer-select')
        ->click('@document-inline-customer')
        ->type('First name', 'Inline')
        ->type('Last name', 'Customer')
        ->click('Create customer')
        ->assertValue('Description', 'Unsaved Quote sentinel')
        ->assertSee('Inline Customer')
        ->assertValue('Description', 'Unsaved Quote sentinel')
        ->click('@document-product-select-0')
        ->click('@document-inline-product')
        ->type('Name', 'Inline Browser Product')
        ->click('Add entry')
        ->assertValue('Product or Service', 'Inline Browser Product')
        ->assertValue('Description', '')
        ->type('Description', 'Inline Browser Product')
        ->type('Item price', '100')
        ->assertSee('Inline Customer')
        ->click('Save quote draft')
        ->assertSee('Quote saved.')
        ->assertSee('214.20')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->click('View')
        ->assertSee('Current quote')
        ->assertSee('PO-BROWSER-42')
        ->assertSee('Inline Customer')
        ->assertSee('214.20')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->navigate(route('quotes.index', $company, false))
        ->assertSee('PO-BROWSER-42')
        ->assertSee('Draft')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->navigate(route('company-number-series.edit', $company, false))
        ->assertSee('Quote counter realignment')
        ->type('Next sequence value', '10')
        ->type('Reason for realignment', 'Reserved external range')
        ->click('Realign Quote counter')
        ->assertSee('Quote counter realigned.')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('keeps the Romanian Quote Draft editor usable on a narrow viewport', function () {
    [$owner, $company] = companyForQuoteBrowser('ro');

    openQuoteCreate($owner, $company, mobile: true)
        ->click('Ofertă nouă')
        ->assertSee('Ofertă nouă')
        ->click('Adaugă produs sau serviciu')
        ->assertSee('Descriere')
        ->assertSee('Preț unitar')
        ->assertSee('Salvează oferta')
        ->click('Salvează oferta')
        ->assertSee('Oferta a fost salvată.')
        ->assertSee('Q-'.now('Europe/Bucharest')->year.'-0001')
        ->click('Vizualizează')
        ->assertSee('Oferta curentă')
        ->assertSee('Quote Browser SRL')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->navigate(route('quotes.index', $company, false))
        ->assertSee('Oferte')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('converts and unlinks an accepted Quote without desktop overflow', function () {
    [$owner, $company] = companyForQuoteBrowser();
    $quote = acceptedQuoteForBrowser($company, $owner);

    openQuoteCreate($owner, $company)
        ->navigate(route('quotes.edit', [$company, $quote], false))
        ->assertSee('Invoice allocation')
        ->click('@convert-quote')
        ->assertSee('Create an Invoice from this Quote?')
        ->click('@confirm-convert-quote')
        ->assertSee('I-'.now('Europe/Bucharest')->year.'-0001')
        ->click('I-'.now('Europe/Bucharest')->year.'-0001')
        ->assertSee('I-'.now('Europe/Bucharest')->year.'-0001')
        ->assertValue('Customer reference / PO number', 'PO-CONVERT-42')
        ->navigate(route('quotes.edit', [$company, $quote], false))
        ->assertSee('100.00 RON')
        ->click('Unlink')
        ->type('Reason', 'Independent browser billing')
        ->check('I confirm that this Draft Invoice should become independent.')
        ->click('Unlink Invoice')
        ->assertSee('Invoice unlinked from the Quote.')
        ->assertSee('No Invoices have been created from this Quote.')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('creates views and revokes a secure Quote link without desktop overflow', function () {
    [$owner, $company] = companyForQuoteBrowser();
    $quote = app(CreateQuoteDraft::class)->handle($company, $owner, (string) Str::uuid7());
    app(TenantContext::class)->runAsSystem($company->id, function () use ($quote): void {
        Document::query()->whereKey($quote->id)->update([
            'customer_id' => Customer::query()->sole()->id,
        ]);
        Quote::query()->whereKey($quote->id)->update(['lifecycle' => QuoteLifecycle::Sent]);
    });
    $page = openQuoteCreate($owner, $company)
        ->navigate(route('quotes.edit', [$company, $quote], false))
        ->assertSee('Secure public link')
        ->assertSee('Not created')
        ->click('Create secure link')
        ->assertSee('Secure public link created.')
        ->assertSee('Active');
    $token = app(TenantContext::class)->runAsSystem(
        $company->id,
        fn (): string => PublicDocumentLink::query()->sole()->token_ciphertext,
    );

    $page->navigate(route('public-quotes.show', $token, false))
        ->assertSee('Quote '.$quote->rendered_number)
        ->assertSee('Download PDF')
        ->assertSee('Respond to this quote')
        ->type('Your name', 'Browser Customer')
        ->type('Your email address', 'browser-customer@example.com')
        ->click('Accept quote')
        ->assertSee('Quote accepted')
        ->assertSee('Securely shared with Invumo')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->navigate(route('quotes.edit', [$company, $quote], false))
        ->click('Revoke link')
        ->assertSee('Secure public link revoked.')
        ->assertSee('Disabled')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('shows Romanian conversion controls on mobile without overflow', function () {
    [$owner, $company] = companyForQuoteBrowser('ro');
    $quote = acceptedQuoteForBrowser($company, $owner);

    openQuoteCreate($owner, $company, mobile: true)
        ->navigate(route('quotes.edit', [$company, $quote], false))
        ->assertSee('Alocarea facturilor')
        ->click('@convert-quote')
        ->assertSee('Creezi o factură din această ofertă?')
        ->assertSee('Creează factura ciornă')
        ->click('Anulează')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
