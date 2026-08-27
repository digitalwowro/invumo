<?php

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Actions\CreatePublicDocumentLink;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Quotes\Actions\CreateQuoteDraft;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Models\Quote;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

afterEach(function (): void {
    Company::query()->pluck('id')->each(fn (string $companyId) => app(TenantContext::class)
        ->runAsSystem($companyId, fn () => Quote::query()->update([
            'lifecycle' => QuoteLifecycle::Draft,
        ])));
});

it('rejects a Romanian public Quote on mobile without overflow', function () {
    $owner = User::factory()->create(['language_code' => 'ro']);
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);
    $company = app(CreateCompany::class)->handle($account, $owner, 'Ofertă Publică SRL');
    app(TenantContext::class)->runAsSystem($company->id, function (): void {
        CompanySetting::query()->firstOrFail()->update([
            'timezone' => 'Europe/Bucharest',
            'default_document_language' => 'ro',
        ]);
        CompanyCurrency::query()->create([
            'currency_code' => 'RON',
            'currency_precision' => 2,
            'is_default' => true,
            'active' => true,
        ]);
    });
    $quote = app(CreateQuoteDraft::class)->handle($company, $owner, (string) Str::uuid7());
    app(TenantContext::class)->runAsSystem($company->id, fn () => Quote::query()
        ->whereKey($quote->id)->update(['lifecycle' => QuoteLifecycle::Sent]));
    $link = app(CreatePublicDocumentLink::class)->handle(
        $company,
        $owner,
        $quote->id,
        DocumentKind::Quote,
    );

    $page = visit(route('public-quotes.show', $link->token_ciphertext, false))
        ->on()->iPhone15()
        ->assertSee('Răspunde la această ofertă')
        ->type('Numele tău', 'Client Mobil')
        ->type('Adresa ta de e-mail', 'client@example.com')
        ->click('Respinge oferta')
        ->assertSee('Ofertă respinsă')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
