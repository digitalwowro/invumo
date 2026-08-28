<?php

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Data\EmailDeliveryAttemptState;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Data\PublicDocumentToken;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryAttempt;
use App\Modules\Delivery\Models\EmailDeliveryRecipient;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Quotes\Actions\CreateQuoteDraft;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

function deliveryBrowserContext(string $locale): array
{
    $owner = User::factory()->create([
        'name' => 'Delivery Owner',
        'email' => "delivery-{$locale}@example.com",
        'language_code' => $locale,
    ]);
    $account = Account::query()->create([
        'owner_user_id' => $owner->id,
        'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
    ]);
    $company = app(CreateCompany::class)->handle($account, $owner, 'Delivery Browser SRL');
    app(TenantContext::class)->runAsSystem($company->id, function () use ($locale): void {
        CompanySetting::query()->firstOrFail()->update([
            'timezone' => 'Europe/Bucharest',
            'default_document_language' => $locale,
        ]);
        CompanyCurrency::query()->create([
            'currency_code' => 'RON', 'currency_precision' => 2,
            'is_default' => true, 'active' => true,
        ]);
    });
    $quote = app(CreateQuoteDraft::class)->handle($company, $owner, (string) Str::uuid7());
    app(TenantContext::class)->runAsSystem($company->id, function () use ($quote): void {
        $token = PublicDocumentToken::fromBytes(random_bytes(PublicDocumentToken::BYTES));
        $link = PublicDocumentLink::query()->create([
            'document_id' => $quote->id,
            'generation' => 1,
            'token_hash' => $token->hash,
            'token_ciphertext' => $token->plainText,
            'expires_at' => now()->addDays(30),
        ]);
        DocumentDeliverySetting::query()->where('document_id', $quote->id)->update([
            'public_access_enabled' => true,
        ]);
        $delivery = EmailDelivery::query()->create([
            'document_id' => $quote->id,
            'public_document_link_id' => $link->id,
            'document_kind' => DocumentKind::Quote,
            'event_type' => EmailTemplateEvent::QuoteSent,
            'delivery_key' => (string) Str::uuid7(),
            'document_edit_version' => $quote->edit_version,
            'language_code' => 'en',
            'subject' => 'Delivery history subject',
            'body' => 'Delivery history body',
            'button_label' => 'View',
            'button_url' => 'https://app.invumo.test/q/example',
            'attachment_mode' => 'SECURE_LINK_ONLY',
            'provider_name' => 'ZEPTOMAIL',
            'dispatch_state' => EmailDeliveryState::Rejected,
            'failure_category' => 'provider_permanent_rejection',
            'failure_summary' => 'Provider rejection.',
            'failed_at' => now(),
        ]);
        EmailDeliveryRecipient::query()->create([
            'delivery_id' => $delivery->id, 'role' => 'TO',
            'email' => 'customer@example.com', 'display_order' => 1,
        ]);
        EmailDeliveryAttempt::query()->create([
            'delivery_id' => $delivery->id, 'attempt_number' => 1,
            'client_reference' => (string) Str::uuid7(),
            'state' => EmailDeliveryAttemptState::PermanentRejection,
            'failure_category' => 'provider_permanent_rejection',
            'failure_summary' => 'Provider rejection.',
            'submitted_at' => now(), 'completed_at' => now(),
        ]);
    });

    return [$owner, $company, $quote];
}

it('keeps English direct delivery composer and retry history usable on desktop', function () {
    [$owner, $company, $quote] = deliveryBrowserContext('en');

    visit('/login')->on()->desktop()
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('quotes.edit', [$company, $quote], false))
        ->assertSee('Email delivery')
        ->assertSee('The provider rejected the email.')
        ->click('Retry')
        ->assertSee('Create a new provider attempt?')
        ->click('Cancel')
        ->click('Send email')
        ->assertSee('Send document email')
        ->wait(0.3)
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('keeps Romanian direct delivery history and composer usable on mobile', function () {
    [$owner, $company, $quote] = deliveryBrowserContext('ro');

    visit('/login')->on()->iPhone15()
        ->type('Email address', $owner->email)
        ->type('Password', 'password')
        ->click('Log in')
        ->navigate(route('quotes.edit', [$company, $quote], false))
        ->assertSee('Livrare prin email')
        ->assertSee('Furnizorul a respins emailul.')
        ->click('Trimite emailul')
        ->assertSee('Trimite documentul prin email')
        ->wait(0.3)
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
