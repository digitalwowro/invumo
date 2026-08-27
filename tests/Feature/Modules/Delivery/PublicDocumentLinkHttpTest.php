<?php

namespace Tests\Feature\Modules\Delivery;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Models\Quote;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Smalot\PdfParser\Parser;
use Tests\Support\PublicDocumentTestCase;

final class PublicDocumentLinkHttpTest extends PublicDocumentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-08-27 12:00:00 Europe/Bucharest');
    }

    protected function tearDown(): void
    {
        Company::query()->pluck('id')->each(fn (string $companyId) => app(TenantContext::class)
            ->runAsSystem($companyId, function (): void {
                DB::connection(config('database.tenant_connection'))->transaction(function (): void {
                    Invoice::query()->where('lifecycle', InvoiceLifecycle::Cancelled)
                        ->update(['lifecycle' => InvoiceLifecycle::Issued]);
                    Invoice::query()->where('lifecycle', InvoiceLifecycle::Issued)
                        ->update(['lifecycle' => InvoiceLifecycle::Draft]);
                    Quote::query()->where('lifecycle', QuoteLifecycle::Sent)
                        ->update(['lifecycle' => QuoteLifecycle::Draft]);
                });
            }));
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_member_creates_and_publicly_reads_a_quote_and_pdf_without_side_effects(): void
    {
        [$owner, $company] = $this->company();
        $member = User::factory()->create();
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $quote = $this->quote($company, $owner);
        Queue::fake();

        $this->actingAs($member)
            ->post(route('quotes.public-link.store', [$company, $quote]))
            ->assertRedirect()
            ->assertSessionHas('status');
        $token = $this->currentToken($company, $quote->id);
        $before = $this->tenant($company, fn (): array => [
            PublicDocumentLink::query()->count(),
            AuditEvent::query()->count(),
        ]);
        Inertia::flushShared();

        $response = $this->get(route('public-quotes.show', $token))
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/document')
                ->where('document.number', $quote->rendered_number)
                ->where('document.kind', 'Quote')
                ->where('i18n.locale', 'en')
                ->missing('auth')
                ->missing('companyContext')
                ->where('pdfUrl', route('public-quotes.pdf', $token, false)));
        $this->assertTrue($response->baseResponse->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->baseResponse->headers->hasCacheControlDirective('no-store'));
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $response->headers->get('Content-Security-Policy'));

        $pdf = $this->get(route('public-quotes.pdf', $token))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload($quote->rendered_number.'.pdf');
        $this->assertStringContainsString('Quote', (new Parser)->parseContent($pdf->getContent())->getText());
        $this->assertSame($before, $this->tenant($company, fn (): array => [
            PublicDocumentLink::query()->count(),
            AuditEvent::query()->count(),
        ]));
        Queue::assertNothingPushed();
        app(TenantContext::class)->assertClear();
    }

    public function test_revocation_regeneration_expiry_and_wrong_kind_fail_closed(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->quote($company, $owner);
        $this->actingAs($owner)->post(route('quotes.public-link.store', [$company, $quote]));
        $first = $this->currentToken($company, $quote->id);

        $this->get(route('public-invoices.show', $first))->assertNotFound();
        $this->post(route('quotes.public-link.regenerate', [$company, $quote]))
            ->assertRedirect()
            ->assertSessionHas('status');
        $second = $this->currentToken($company, $quote->id);
        $this->assertNotSame($first, $second);
        $this->get(route('public-quotes.show', $first))->assertNotFound();
        $this->get(route('public-quotes.show', $second))->assertOk();

        $this->delete(route('quotes.public-link.destroy', [$company, $quote]))
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->get(route('public-quotes.show', $second))->assertNotFound();
        $this->post(route('quotes.public-link.store', [$company, $quote]))->assertRedirect();
        $third = $this->currentToken($company, $quote->id);
        Date::setTestNow('2036-08-28 12:00:00 Europe/Bucharest');
        $this->get(route('public-quotes.show', $third))->assertNotFound();

        $this->tenant($company, function () use ($quote): void {
            $this->assertSame(3, PublicDocumentLink::query()->where('document_id', $quote->id)->count());
            $this->assertSame(1, PublicDocumentLink::query()->whereNull('revoked_at')->count());
        });
    }

    public function test_company_account_latch_and_malformed_token_are_indistinguishable(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->quote($company, $owner);
        $this->actingAs($owner)->post(route('quotes.public-link.store', [$company, $quote]));
        $token = $this->currentToken($company, $quote->id);

        $this->tenant($company, fn () => DocumentDeliverySetting::query()
            ->where('document_id', $quote->id)->update(['public_access_enabled' => false]));
        $this->get(route('public-quotes.show', $token))->assertNotFound();
        $this->get('/q/not-a-valid-token')->assertNotFound();

        $this->tenant($company, fn () => DocumentDeliverySetting::query()
            ->where('document_id', $quote->id)->update(['public_access_enabled' => true]));
        $company->owningAccount()->update(['suspended_at' => now()]);
        $this->get(route('public-quotes.show', $token))->assertNotFound();
        $company->owningAccount()->update(['suspended_at' => null]);
        $company->update(['archived_at' => now()]);
        $this->get(route('public-quotes.show', $token))->assertNotFound();
    }

    public function test_expired_quotes_and_cancelled_invoices_remain_viewable_with_current_state(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->quote($company, $owner);
        $invoice = $this->invoice($company, $owner);
        $this->tenant($company, function () use ($quote, $invoice): void {
            Quote::query()->whereKey($quote->id)->update(['lifecycle' => QuoteLifecycle::Sent]);
            Invoice::query()->whereKey($invoice->id)->update([
                'lifecycle' => InvoiceLifecycle::Issued,
            ]);
            Invoice::query()->whereKey($invoice->id)->update([
                'lifecycle' => InvoiceLifecycle::Cancelled,
            ]);
        });
        Date::setTestNow('2026-10-01 12:00:00 Europe/Bucharest');
        $this->actingAs($owner)->post(route('quotes.public-link.store', [$company, $quote]));
        $this->post(route('invoices.public-link.store', [$company, $invoice]));
        $quoteToken = $this->currentToken($company, $quote->id);
        $invoiceToken = $this->currentToken($company, $invoice->id);

        Inertia::flushShared();
        $this->get(route('public-quotes.show', $quoteToken))
            ->assertInertia(fn (Assert $page) => $page->where('document.status', 'Expired'));
        $this->get(route('public-invoices.show', $invoiceToken))
            ->assertInertia(fn (Assert $page) => $page->where('document.status', 'Cancelled'));
    }

    public function test_cross_company_and_unauthorized_internal_management_are_hidden(): void
    {
        [$owner, $company] = $this->company();
        [$outsider, $other] = $this->company('Other Public SRL');
        $quote = $this->quote($company, $owner);

        $this->actingAs($outsider)
            ->post(route('quotes.public-link.store', [$other, $quote]))
            ->assertNotFound();
        $this->actingAs(User::factory()->create())
            ->post(route('quotes.public-link.store', [$company, $quote]))
            ->assertNotFound();
        $this->assertSame(0, $this->tenant(
            $company,
            fn (): int => PublicDocumentLink::query()->count(),
        ));
    }
}
