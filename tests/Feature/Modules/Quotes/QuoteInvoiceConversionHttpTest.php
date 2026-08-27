<?php

namespace Tests\Feature\Modules\Quotes;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentCompanySnapshot;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Quotes\Actions\CreateQuoteDraft;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Models\QuoteInvoiceLink;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class QuoteInvoiceConversionHttpTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-08-26 12:00:00 Europe/Bucharest');
    }

    public function test_member_converts_an_accepted_quote_idempotently_with_exact_snapshots(): void
    {
        [$owner, $company] = $this->company();
        $member = User::factory()->create();
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $quote = $this->completeQuote($company, $owner, QuoteLifecycle::Accepted);
        $this->tenant($company, fn () => DocumentDeliverySetting::query()
            ->where('document_id', $quote->id)->update(['public_access_enabled' => true]));
        $key = (string) Str::uuid7();
        $this->actingAs($member);

        $first = $this->post(route('quotes.invoices.store', [$company, $quote]), [
            'creation_key' => $key,
            'confirmed_override' => false,
        ])->assertRedirect()->assertSessionHas('status');
        $this->post(route('quotes.invoices.store', [$company, $quote]), [
            'creation_key' => $key,
            'confirmed_override' => false,
        ])->assertRedirect($first->headers->get('Location'));

        $this->tenant($company, function () use ($quote): void {
            $link = QuoteInvoiceLink::query()->sole();
            $invoiceDocument = Document::query()->findOrFail($link->invoice_id);
            $invoice = Invoice::query()->findOrFail($link->invoice_id);
            $sourceSnapshot = DocumentCompanySnapshot::query()
                ->where('document_id', $quote->id)->sole();
            $invoiceSnapshot = DocumentCompanySnapshot::query()
                ->where('document_id', $invoiceDocument->id)->sole();

            $this->assertSame('I-2026-0001', $invoiceDocument->rendered_number);
            $this->assertSame('RON', $invoiceDocument->currency_code);
            $this->assertSame(2, $invoiceDocument->currency_precision);
            $this->assertSame('PO-42', $invoiceDocument->customer_reference);
            $this->assertSame('Quote terms', $invoiceDocument->terms_and_conditions);
            $this->assertSame('Invoice instructions', $invoiceDocument->notes);
            $this->assertSame('100.00000000', $invoiceDocument->total);
            $this->assertSame(15, $invoice->payment_term_days);
            $this->assertSame('2026-09-10', $invoice->due_date?->toDateString());
            $this->assertSame($sourceSnapshot->legal_name, $invoiceSnapshot->legal_name);
            $this->assertSame(1, DocumentLine::query()
                ->where('document_id', $invoiceDocument->id)->count());
            $this->assertTrue(DocumentDeliverySetting::query()
                ->where('document_id', $invoiceDocument->id)->sole()->public_access_enabled);
            $this->assertSame(1, QuoteInvoiceLink::query()->count());
            $this->assertSame(1, AuditEvent::query()
                ->where('action', 'company.quote.converted_to_invoice')->count());
            $encoded = json_encode(
                AuditEvent::query()->where('action', 'company.invoice.created_from_quote')->sole()->after,
                JSON_THROW_ON_ERROR,
            );
            $this->assertStringNotContainsString('Quote terms', $encoded);
            $this->assertStringNotContainsString('Invoice instructions', $encoded);
        });

        $this->post(route('quotes.invoices.store', [$company, $quote]), [
            'creation_key' => (string) Str::uuid7(),
            'confirmed_override' => false,
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->get(route('quotes.edit', [$company, $quote]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoiceAllocation.quoted', '100.00')
                ->where('invoiceAllocation.invoiced', '200.00')
                ->where('invoiceAllocation.remaining', '-100.00')
                ->where('invoiceAllocation.willOverAllocate', true)
                ->has('invoiceAllocation.invoices', 2));
        $this->resetQuoteLifecycles($company);
    }

    public function test_non_accepted_conversion_requires_owner_or_admin_confirmation_and_rejected_is_blocked(): void
    {
        [$owner, $company] = $this->company();
        $member = User::factory()->create();
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $draft = $this->completeQuote($company, $owner, QuoteLifecycle::Draft);
        $this->actingAs($member)->post(route('quotes.invoices.store', [$company, $draft]), [
            'creation_key' => (string) Str::uuid7(),
            'confirmed_override' => true,
        ])->assertForbidden();

        $this->actingAs($owner)->post(route('quotes.invoices.store', [$company, $draft]), [
            'creation_key' => (string) Str::uuid7(),
            'confirmed_override' => false,
        ])->assertSessionHasErrors('conversion');
        $this->post(route('quotes.invoices.store', [$company, $draft]), [
            'creation_key' => (string) Str::uuid7(),
            'confirmed_override' => true,
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $rejected = $this->completeQuote($company, $owner, QuoteLifecycle::Rejected);
        $this->post(route('quotes.invoices.store', [$company, $rejected]), [
            'creation_key' => (string) Str::uuid7(),
            'confirmed_override' => true,
        ])->assertSessionHasErrors('conversion');
        $this->resetQuoteLifecycles($company);
    }

    public function test_unlink_is_admin_only_preserves_the_invoice_and_releases_quote_guards(): void
    {
        [$owner, $company] = $this->company();
        $member = User::factory()->create();
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $quote = $this->completeQuote($company, $owner, QuoteLifecycle::Accepted);
        $this->actingAs($owner)->post(route('quotes.invoices.store', [$company, $quote]), [
            'creation_key' => (string) Str::uuid7(),
            'confirmed_override' => false,
        ])->assertRedirect();
        $invoiceId = $this->tenant(
            $company,
            fn (): string => QuoteInvoiceLink::query()->sole()->invoice_id,
        );
        $url = route('quotes.invoices.unlink', [$company, $quote, $invoiceId]);

        $this->actingAs($member)->post($url, [
            'reason' => 'Not mine', 'confirmed' => true,
        ])->assertForbidden();
        $this->actingAs($owner)->delete(route('quotes.destroy', [$company, $quote]), [
            'confirmed' => true, 'confirmed_high_risk' => true,
        ])->assertSessionHasErrors('quote');

        $this->post($url, ['reason' => 'Independent billing', 'confirmed' => true])
            ->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->tenant($company, function () use ($invoiceId): void {
            $this->assertSame(0, QuoteInvoiceLink::query()->count());
            $this->assertNotNull(Document::query()->find($invoiceId));
            $audit = AuditEvent::query()->where('action', 'company.quote.invoice_unlinked')->sole();
            $this->assertSame('Independent billing', $audit->reason);
            $this->assertSame(false, $audit->after['linked']);
        });
        $this->resetQuoteLifecycles($company);
    }

    public function test_application_scope_and_forced_rls_hide_cross_company_provenance(): void
    {
        [$ownerA, $companyA] = $this->company();
        [$ownerB, $companyB] = $this->company();
        $quote = $this->completeQuote($companyA, $ownerA, QuoteLifecycle::Accepted);
        $this->actingAs($ownerA)->post(route('quotes.invoices.store', [$companyA, $quote]), [
            'creation_key' => (string) Str::uuid7(),
            'confirmed_override' => false,
        ])->assertRedirect();

        $this->actingAs($ownerB)->post(route('quotes.invoices.store', [$companyB, $quote]), [
            'creation_key' => (string) Str::uuid7(),
            'confirmed_override' => false,
        ])->assertNotFound();
        $this->assertSame(0, DB::connection('pgsql_schema')->table('quote_invoice_links')->count());
        $this->assertSame(1, $this->tenant(
            $companyA,
            fn (): int => QuoteInvoiceLink::query()->count(),
        ));
        $this->assertSame(0, $this->tenant(
            $companyB,
            fn (): int => QuoteInvoiceLink::query()->count(),
        ));
        $this->resetQuoteLifecycles($companyA);
    }

    /** @return array{User, Company} */
    private function company(): array
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Conversion SRL');
        $this->tenant($company, function (): void {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'Europe/Bucharest',
                'default_document_language' => 'en',
                'default_payment_term_days' => 15,
                'default_invoice_notes' => 'Invoice instructions',
                'legal_name' => 'Conversion Legal SRL',
            ]);
            CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]);
        });

        return [$owner, $company];
    }

    private function completeQuote(
        Company $company,
        User $actor,
        QuoteLifecycle $lifecycle,
    ): Document {
        $document = app(CreateQuoteDraft::class)->handle($company, $actor, (string) Str::uuid7());

        return $this->tenant($company, function () use ($document, $lifecycle): Document {
            $document->update([
                'customer_reference' => 'PO-42',
                'terms_and_conditions' => 'Quote terms',
                'subtotal' => '100', 'tax_total' => '0', 'total' => '100',
            ]);
            Quote::query()->whereKey($document->id)->update(['lifecycle' => $lifecycle]);
            DocumentLine::query()->create([
                'document_id' => $document->id, 'position' => 1,
                'description' => 'Consulting', 'item_price' => '100', 'quantity' => '1',
                'unit' => 'hour', 'period_unit' => 'NONE', 'period_quantity' => null,
                'discount_percentage' => '0', 'discount_amount' => '0',
                'tax_name' => null, 'tax_percentage' => '0',
                'items_subtotal' => '100', 'items_total' => '100',
                'grand_subtotal' => '100', 'tax_amount' => '0',
                'final_line_total' => '100',
            ]);

            return $document->refresh();
        });
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }

    private function resetQuoteLifecycles(Company $company): void
    {
        $this->tenant($company, fn () => Quote::query()->update(['lifecycle' => QuoteLifecycle::Draft]));
    }
}
