<?php

namespace Tests\Feature\Modules\Invoices;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Customers\Data\CustomerType;
use App\Modules\Customers\Models\Customer;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentCustomerSnapshot;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Documents\Models\DocumentNumberEvent;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Invoices\Actions\CreateInvoiceDraft;
use App\Modules\Invoices\Actions\IssueInvoice;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Quotes\Actions\CreateQuoteDraft;
use App\Modules\Quotes\Models\QuoteInvoiceLink;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InvoiceDeletionHttpTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-08-27 12:00:00');
    }

    protected function tearDown(): void
    {
        Company::query()->pluck('id')->each(fn (string $companyId) => app(TenantContext::class)
            ->runAsSystem($companyId, function (): void {
                DB::connection(config('database.tenant_connection'))->transaction(function (): void {
                    Invoice::query()->where('lifecycle', InvoiceLifecycle::Cancelled)
                        ->update(['lifecycle' => InvoiceLifecycle::Issued]);
                    DB::statement('SET CONSTRAINTS invoice_transaction_ledger_trigger DEFERRED');
                    InvoiceTransaction::query()->delete();
                    Invoice::query()->where('lifecycle', InvoiceLifecycle::Issued)
                        ->update(['lifecycle' => InvoiceLifecycle::Draft]);
                });
            }));
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_owner_and_admin_delete_transaction_free_draft_issued_and_cancelled_invoices(): void
    {
        [$company, $owner] = $this->company();
        $admin = User::factory()->create();
        $company->memberships()->create(['user_id' => $admin->id, 'role' => CompanyRole::Admin]);
        $draft = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());
        $issued = $this->issuedInvoice($company, $owner);
        $cancelled = $this->issuedInvoice($company, $owner);
        $this->tenant($company, fn () => Invoice::query()->whereKey($cancelled->id)
            ->update(['lifecycle' => InvoiceLifecycle::Cancelled]));

        $this->actingAs($owner)->delete(route('invoices.destroy', [$company, $draft]), [
            'confirmed' => true, 'confirmed_high_risk' => false,
        ])->assertRedirect(route('invoices.index', $company));
        $this->actingAs($admin)->delete(route('invoices.destroy', [$company, $issued]), [
            'confirmed' => true, 'confirmed_high_risk' => false,
        ])->assertSessionHasErrors('invoice');
        $this->delete(route('invoices.destroy', [$company, $issued]), [
            'confirmed' => true, 'confirmed_high_risk' => true,
            'confirmation_number' => 'wrong-number',
        ])->assertSessionHasErrors('confirmation_number');

        foreach ([$issued, $cancelled] as $invoice) {
            $this->delete(route('invoices.destroy', [$company, $invoice]), [
                'confirmed' => true, 'confirmed_high_risk' => true,
                'confirmation_number' => $invoice->rendered_number,
            ])->assertRedirect(route('invoices.index', $company));
        }

        $this->tenant($company, function () use ($draft, $issued): void {
            $this->assertSame(0, Document::query()->count());
            $this->assertSame(3, DocumentNumberEvent::query()->where('event_type', 'DELETED')->count());
            $audit = AuditEvent::query()->where('action', 'company.invoice.deleted')
                ->where('target_id', $issued->id)->sole();
            $this->assertEqualsCanonicalizing(
                ['document_number', 'lifecycle', 'had_public_link_history', 'had_delivery_history'],
                array_keys($audit->before),
            );
            $this->assertNull($audit->after);
            $payload = json_encode($audit->before, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('Deletion Customer', $payload);
            $this->assertSame(1, DocumentNumberEvent::query()
                ->where('document_id', $draft->id)->where('event_type', 'DELETED')->count());
        });
        $next = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());
        $this->assertNotContains($next->rendered_number, [
            $draft->rendered_number, $issued->rendered_number, $cancelled->rendered_number,
        ]);
    }

    public function test_member_is_denied_and_cross_company_invoice_is_hidden_by_application_and_rls(): void
    {
        [$company, $owner] = $this->company();
        $member = User::factory()->create();
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $invoice = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());

        $this->actingAs($member)->get(route('invoices.edit', [$company, $invoice]))
            ->assertInertia(fn (Assert $page) => $page->where('deletion.url', null));
        $this->delete(route('invoices.destroy', [$company, $invoice]), [
            'confirmed' => true, 'confirmed_high_risk' => false,
        ])->assertForbidden();

        [$other, $otherOwner] = $this->company();
        $foreign = app(CreateInvoiceDraft::class)->handle($other, $otherOwner, (string) Str::uuid7());
        $this->actingAs($owner)->delete(route('invoices.destroy', [$company, $foreign]), [
            'confirmed' => true, 'confirmed_high_risk' => false,
        ])->assertNotFound();
        $this->tenant($company, fn () => $this->assertNull(Document::query()->find($foreign->id)));
    }

    public function test_any_transaction_blocks_application_and_database_deletion(): void
    {
        [$company, $owner] = $this->company();
        $invoice = $this->issuedInvoice($company, $owner);
        $this->tenant($company, fn () => InvoiceTransaction::query()->create([
            'invoice_id' => $invoice->id,
            'kind' => 'PAYMENT',
            'amount' => '10',
            'currency_code' => 'RON',
            'currency_precision' => 2,
            'transaction_date' => '2026-08-27',
            'creation_key' => (string) Str::uuid7(),
            'edit_version' => 1,
        ]));

        $this->actingAs($owner)->delete(route('invoices.destroy', [$company, $invoice]), [
            'confirmed' => true, 'confirmed_high_risk' => true,
            'confirmation_number' => $invoice->rendered_number,
        ])->assertSessionHasErrors('invoice');

        try {
            $this->tenant($company, fn () => DB::connection(config('database.tenant_connection'))
                ->transaction(fn () => Document::query()->findOrFail($invoice->id)->delete()));
            $this->fail('The restrictive transaction foreign key must reject Invoice deletion.');
        } catch (QueryException $exception) {
            $this->assertSame('23001', $exception->errorInfo[0]);
        }
    }

    public function test_quote_provenance_blocks_deletion_until_the_guarded_unlink_completes(): void
    {
        [$company, $owner] = $this->company();
        $quote = app(CreateQuoteDraft::class)->handle($company, $owner, (string) Str::uuid7());
        $invoice = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());
        $this->tenant($company, fn () => QuoteInvoiceLink::query()->create([
            'quote_id' => $quote->id,
            'invoice_id' => $invoice->id,
            'copied_by_user_id' => $owner->id,
            'creation_key' => (string) Str::uuid7(),
            'copied_at' => now(),
        ]));

        $this->actingAs($owner)->delete(route('invoices.destroy', [$company, $invoice]), [
            'confirmed' => true, 'confirmed_high_risk' => false,
        ])->assertSessionHasErrors('invoice');
        $this->tenant($company, function () use ($quote, $invoice): void {
            $this->assertNotNull(Document::query()->find($quote->id));
            $this->assertNotNull(Document::query()->find($invoice->id));
            $this->assertSame(1, QuoteInvoiceLink::query()->count());
            $this->assertSame(0, AuditEvent::query()->where('action', 'company.invoice.deleted')->count());
        });

        $this->post(route('quotes.invoices.unlink', [$company, $quote, $invoice]), [
            'reason' => 'Invoice must be deleted independently',
            'confirmed' => true,
        ])->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->delete(route('invoices.destroy', [$company, $invoice]), [
            'confirmed' => true, 'confirmed_high_risk' => false,
        ])->assertRedirect(route('invoices.index', $company));

        $this->tenant($company, function () use ($quote, $invoice): void {
            $this->assertNotNull(Document::query()->find($quote->id));
            $this->assertNull(Document::query()->find($invoice->id));
            $unlink = AuditEvent::query()->where('action', 'company.quote.invoice_unlinked')->sole();
            $this->assertSame($quote->id, $unlink->target_id);
            $this->assertSame('Invoice must be deleted independently', $unlink->reason);
            $this->assertSame(true, $unlink->before['linked']);
            $this->assertSame(false, $unlink->after['linked']);
        });
    }

    /** @return array{Company, User} */
    private function company(): array
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Deletion Test SRL');
        $this->tenant($company, function (): void {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'Europe/Bucharest',
                'default_document_language' => 'en',
                'default_payment_term_days' => 30,
            ]);
            CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]);
        });

        return [$company, $owner];
    }

    private function issuedInvoice(Company $company, User $owner): Document
    {
        $document = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());
        $this->tenant($company, function () use ($document): void {
            $customer = Customer::query()->create([
                'type' => CustomerType::Company, 'legal_name' => 'Deletion Customer SRL',
            ]);
            $document->update([
                'customer_id' => $customer->id, 'issue_date' => '2026-08-27',
                'subtotal' => '100', 'total' => '100',
            ]);
            Invoice::query()->whereKey($document->id)->update(['due_date' => '2026-09-26']);
            DocumentCustomerSnapshot::query()->create([
                'document_id' => $document->id, 'type' => CustomerType::Company,
                'legal_name' => 'Deletion Customer SRL',
            ]);
            DocumentLine::query()->create([
                'document_id' => $document->id, 'position' => 1,
                'description' => 'Deletion service', 'item_price' => '100',
                'quantity' => '1', 'unit' => 'item', 'period_unit' => 'NONE',
                'discount_percentage' => '0', 'discount_amount' => '0',
                'tax_name' => 'VAT', 'tax_percentage' => '0',
                'items_subtotal' => '100', 'items_total' => '100',
                'grand_subtotal' => '100', 'tax_amount' => '0', 'final_line_total' => '100',
            ]);
        });
        app(IssueInvoice::class)->handle($company, $owner, $document->id, 1);

        return $document;
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
