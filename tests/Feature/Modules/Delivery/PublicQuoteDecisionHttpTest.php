<?php

namespace Tests\Feature\Modules\Delivery;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Customers\Data\CustomerType;
use App\Modules\Customers\Models\Customer;
use App\Modules\Delivery\Actions\CreatePublicDocumentLink;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Models\QuotePublicDecision;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PublicDocumentTestCase;

final class PublicQuoteDecisionHttpTest extends PublicDocumentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-08-27 12:00:00 Europe/Bucharest');
    }

    protected function tearDown(): void
    {
        Company::query()->pluck('id')->each(fn (string $companyId) => app(TenantContext::class)
            ->runAsSystem($companyId, fn () => Quote::query()->update([
                'lifecycle' => QuoteLifecycle::Draft,
            ])));
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_sent_quote_exposes_form_and_accepts_with_private_identity_and_safe_audit(): void
    {
        [$owner, $company] = $this->company();
        [$quote, $token] = $this->sentQuoteAndToken($company, $owner);
        $key = (string) Str::uuid7();
        $version = $quote->edit_version;
        Inertia::flushShared();

        $this->get(route('public-quotes.show', $token))
            ->assertInertia(fn (Assert $page) => $page
                ->url(route('public-quotes.show', $token, false))
                ->where('decision.state', 'AVAILABLE')
                ->where('decision.locale', 'en')
                ->where('decision.submitUrl', route('public-quotes.decision', $token, false))
                ->where('decision.idempotencyKey', fn (mixed $value): bool => is_string($value)));

        $this->post(route('public-quotes.decision', $token), [
            'decision' => 'accepted',
            'customer_name' => '  Ana Popescu  ',
            'customer_email' => '  ANA@EXAMPLE.COM  ',
            'idempotency_key' => $key,
            'locale' => 'en',
        ])->assertStatus(303)
            ->assertRedirect(route('public-quotes.show', $token, false));

        $this->tenant($company, function () use ($quote, $key, $version, $token): void {
            $decision = QuotePublicDecision::query()->sole();
            $audit = AuditEvent::query()->where('action', 'company.quote.public_decided')->sole();
            $serialized = json_encode($audit->toArray(), JSON_THROW_ON_ERROR);

            $this->assertSame('Ana Popescu', $decision->customer_name);
            $this->assertSame('ana@example.com', $decision->customer_email);
            $this->assertSame($key, $decision->idempotency_key);
            $this->assertSame(QuoteLifecycle::Accepted, Quote::query()->sole()->lifecycle);
            $this->assertSame($version + 1, Document::query()->findOrFail($quote->id)->edit_version);
            $this->assertSame(AuditActorType::PublicCustomer, $audit->actor_type);
            $this->assertNull($audit->actor_user_id);
            $this->assertSame(['lifecycle'], array_keys($audit->before));
            $this->assertSame(['lifecycle', 'edit_version'], array_keys($audit->after));
            $this->assertStringNotContainsString('Ana Popescu', $serialized);
            $this->assertStringNotContainsString('ana@example.com', $serialized);
            $this->assertStringNotContainsString($token, $serialized);
        });

        Inertia::flushShared();
        $this->get(route('public-quotes.show', $token))
            ->assertInertia(fn (Assert $page) => $page
                ->where('decision.state', 'ACCEPTED')
                ->where('decision.submitUrl', null)
                ->where('document.status', 'Accepted'));
    }

    public function test_replays_are_idempotent_and_opposite_decisions_are_rejected(): void
    {
        [$owner, $company] = $this->company();
        [$quote, $token] = $this->sentQuoteAndToken($company, $owner);
        $payload = [
            'decision' => 'REJECTED',
            'customer_name' => 'Client Name',
            'customer_email' => 'client@example.com',
            'idempotency_key' => (string) Str::uuid7(),
            'locale' => 'en',
        ];

        $this->post(route('public-quotes.decision', $token), $payload)->assertStatus(303);
        $this->post(route('public-quotes.decision', $token), $payload)->assertStatus(303);
        $this->post(route('public-quotes.decision', $token), [
            ...$payload,
            'idempotency_key' => (string) Str::uuid7(),
        ])->assertStatus(303);
        $this->post(route('public-quotes.decision', $token), [
            ...$payload,
            'customer_email' => 'different@example.com',
        ])->assertSessionHasErrors('decision');
        $this->post(route('public-quotes.decision', $token), [
            ...$payload,
            'decision' => 'ACCEPTED',
            'idempotency_key' => (string) Str::uuid7(),
        ])->assertSessionHasErrors('decision');

        $this->tenant($company, function () use ($quote): void {
            $this->assertSame(1, QuotePublicDecision::query()->count());
            $this->assertSame(1, AuditEvent::query()
                ->where('action', 'company.quote.public_decided')->count());
            $this->assertSame(QuoteLifecycle::Rejected, Quote::query()->findOrFail($quote->id)->lifecycle);
        });
    }

    public function test_identity_validation_is_localized_and_ineligible_quotes_fail_closed(): void
    {
        [$owner, $company] = $this->company();
        $this->tenant($company, fn () => CompanySetting::query()->firstOrFail()->update([
            'default_document_language' => 'ro',
        ]));
        [$quote, $token] = $this->sentQuoteAndToken($company, $owner);

        $this->post(route('public-quotes.decision', $token), [
            'decision' => 'ACCEPTED',
            'customer_name' => '',
            'customer_email' => 'invalid',
            'idempotency_key' => (string) Str::uuid7(),
            'locale' => 'ro',
        ])->assertRedirect(route('public-quotes.show', $token, false))
            ->assertSessionHasErrors(['customer_name', 'customer_email']);
        $this->assertStringContainsString(
            'obligatoriu',
            session('errors')->first('customer_name'),
        );

        $this->tenant($company, fn () => PublicDocumentLink::query()
            ->where('document_id', $quote->id)->update(['expires_at' => '2026-10-02 00:00:00+03']));
        Date::setTestNow('2026-10-01 12:00:00 Europe/Bucharest');
        $this->post(route('public-quotes.decision', $token), [
            'decision' => 'ACCEPTED',
            'customer_name' => 'Ana',
            'customer_email' => 'ana@example.com',
            'idempotency_key' => (string) Str::uuid7(),
            'locale' => 'ro',
        ])->assertSessionHasErrors('decision');

        $this->tenant($company, function () use ($quote): void {
            $this->assertSame(0, QuotePublicDecision::query()->count());
            $this->assertSame(QuoteLifecycle::Sent, Quote::query()->findOrFail($quote->id)->lifecycle);
        });

        $this->tenant($company, fn () => DocumentDeliverySetting::query()
            ->where('document_id', $quote->id)->update(['public_access_enabled' => false]));
        $this->post(route('public-quotes.decision', $token), [
            'decision' => 'ACCEPTED',
            'customer_name' => 'Ana',
            'customer_email' => 'ana@example.com',
            'idempotency_key' => (string) Str::uuid7(),
            'locale' => 'en',
        ])->assertNotFound();
    }

    /** @return array{Document, string} */
    private function sentQuoteAndToken(Company $company, User $owner): array
    {
        $quote = $this->quote($company, $owner);
        $this->tenant($company, function () use ($quote): void {
            $customer = Customer::query()->create([
                'type' => CustomerType::Company,
                'legal_name' => 'Decision Customer SRL',
            ]);
            Document::query()->whereKey($quote->id)->update(['customer_id' => $customer->id]);
            Quote::query()->whereKey($quote->id)->update(['lifecycle' => QuoteLifecycle::Sent]);
        });
        $link = app(CreatePublicDocumentLink::class)->handle(
            $company,
            $owner,
            $quote->id,
            DocumentKind::Quote,
        );

        return [$quote, $link->token_ciphertext];
    }
}
