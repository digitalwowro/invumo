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
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentCompanySnapshot;
use App\Modules\Documents\Models\DocumentCustomerSnapshot;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Invoices\Actions\CreateInvoiceDraft;
use App\Modules\Invoices\Models\Invoice;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

final class InvoiceRepresentationHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_member_views_localized_current_invoice_on_screen_and_pdf_without_side_effects(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $company = $this->company($owner);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $invoice = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());
        $this->tenant($company, fn () => $this->completeRomanianInvoice($invoice));
        $before = $this->tenant($company, fn (): array => [
            Document::query()->count(),
            DocumentLine::query()->count(),
            AuditEvent::query()->count(),
        ]);
        $this->actingAs($member);

        $this->get(route('invoices.current.show', [$company, $invoice]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('invoices/view')
                ->where('document.kind', 'Factură')
                ->where('document.dueDate', '25 sept. 2026')
                ->where('document.customerReference', 'PO-ȘȚ-42')
                ->where('document.customer.displayName', 'Client Știință SRL')
                ->where('document.lines.0.total', "214,20\u{00A0}RON"));

        $response = $this->get(route('invoices.current.pdf', [$company, $invoice]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload($invoice->rendered_number.'.pdf');
        $this->assertTrue($response->baseResponse->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->baseResponse->headers->hasCacheControlDirective('no-store'));
        $text = (new Parser)->parseContent($response->getContent())->getText();

        $this->assertStringContainsString('Factură', $text);
        $this->assertStringContainsString('DATA SCADENȚEI', $text);
        $this->assertStringContainsString('25 sept. 2026', $text);
        $this->assertStringContainsString('Client Știință SRL', $text);
        $this->assertSame($before, $this->tenant($company, fn (): array => [
            Document::query()->count(),
            DocumentLine::query()->count(),
            AuditEvent::query()->count(),
        ]));
    }

    public function test_application_authorization_and_rls_hide_cross_company_invoice_output(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);
        $other = $this->company($owner);
        $invoice = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());

        $this->actingAs($owner)
            ->get(route('invoices.current.show', [$other, $invoice]))
            ->assertNotFound();
        $this->get(route('invoices.current.pdf', [$other, $invoice]))->assertNotFound();
        $this->tenant($other, fn () => $this->assertNull(Document::query()->find($invoice->id)));
    }

    private function completeRomanianInvoice(Document $document): void
    {
        $document->update([
            'issue_date' => '2026-08-26', 'currency_code' => 'RON', 'currency_precision' => 2,
            'document_language' => 'ro', 'customer_reference' => 'PO-ȘȚ-42',
            'terms_and_conditions' => 'Termeni cu ă â î ș ț.', 'notes' => 'Notă client.',
            'subtotal' => '180', 'tax_total' => '34.2', 'total' => '214.2',
        ]);
        Invoice::query()->whereKey($document->id)->update([
            'payment_term_days' => 30, 'due_date' => '2026-09-25',
        ]);
        DocumentCompanySnapshot::query()->where('document_id', $document->id)->update([
            'legal_name' => 'Compania Știință SRL', 'city' => 'București',
            'country_code' => 'RO', 'currency_display_style' => 'SYMBOL',
        ]);
        DocumentCustomerSnapshot::query()->create([
            'document_id' => $document->id, 'type' => 'COMPANY',
            'legal_name' => 'Client Știință SRL', 'city' => 'Cluj-Napoca', 'country_code' => 'RO',
        ]);
        DocumentLine::query()->create([
            'document_id' => $document->id, 'position' => 1,
            'description' => 'Consultanță și analiză', 'item_price' => '100', 'quantity' => '2',
            'unit' => 'ore', 'period_unit' => 'NONE', 'discount_percentage' => '10',
            'discount_amount' => '20', 'tax_name' => 'TVA', 'tax_percentage' => '19',
            'items_subtotal' => '200', 'items_total' => '200', 'grand_subtotal' => '180',
            'tax_amount' => '34.2', 'final_line_total' => '214.2',
        ]);
    }

    private function company(User $owner): Company
    {
        $account = Account::query()->firstOrCreate(
            ['owner_user_id' => $owner->id],
            ['plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id],
        );
        $company = app(CreateCompany::class)->handle($account, $owner, 'Invoice Output SRL');
        $this->tenant($company, function (): void {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'Europe/Bucharest', 'default_document_language' => 'ro',
                'default_payment_term_days' => 30, 'currency_display_style' => 'SYMBOL',
            ]);
            CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]);
        });

        return $company;
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
