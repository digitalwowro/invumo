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
use App\Modules\Documents\Models\DocumentBankSnapshot;
use App\Modules\Documents\Models\DocumentCompanySnapshot;
use App\Modules\Documents\Models\DocumentCustomerSnapshot;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Quotes\Actions\CreateQuoteDraft;
use App\Modules\Quotes\Models\Quote;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

final class QuoteRepresentationHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_member_views_the_same_localized_current_representation_on_screen_and_pdf_without_side_effects(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $company = $this->company($owner);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $quote = app(CreateQuoteDraft::class)->handle($company, $owner, (string) Str::uuid7());
        $this->tenant($company, fn () => $this->completeRomanianQuote($quote));
        $before = $this->tenant($company, fn (): array => [
            Document::query()->count(),
            DocumentLine::query()->count(),
            AuditEvent::query()->count(),
        ]);
        $this->actingAs($member);

        $this->get(route('quotes.current.show', [$company, $quote]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('quotes/view')
                ->where('document.kind', 'Ofertă')
                ->where('document.number', $quote->rendered_number)
                ->where('document.customerReference', 'PO-ȘȚ-42')
                ->where('document.customer.displayName', 'Client Știință SRL')
                ->where('document.lines.0.description', 'Consultanță și analiză')
                ->where('document.lines.0.total', "214,20\u{00A0}RON")
                ->where('document.total', "214,20\u{00A0}RON")
                ->where('document.termsAndConditions', 'Termeni cu ă â î ș ț.'));

        $pdfResponse = $this->get(route('quotes.current.pdf', [$company, $quote]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertTrue($pdfResponse->baseResponse->headers->hasCacheControlDirective('private'));
        $this->assertTrue($pdfResponse->baseResponse->headers->hasCacheControlDirective('no-store'));
        $text = (new Parser)->parseContent($pdfResponse->getContent())->getText();

        $this->assertStringContainsString('Ofertă', $text);
        $this->assertStringContainsString('PO-ȘȚ-42', $text);
        $this->assertStringContainsString('Client Știință SRL', $text);
        $this->assertStringContainsString('Consultanță și analiză', $text);
        $this->assertStringContainsString("214,20\u{00A0}RON", $text);
        $this->assertStringContainsString('Termeni cu ă â î ș ț.', $text);
        $this->assertSame($before, $this->tenant($company, fn (): array => [
            Document::query()->count(),
            DocumentLine::query()->count(),
            AuditEvent::query()->count(),
        ]));
    }

    public function test_application_authorization_and_forced_rls_hide_cross_company_representations(): void
    {
        $owner = User::factory()->create();
        $account = $this->account($owner);
        $company = $this->company($owner, $account);
        $other = $this->company($owner, $account);
        $quote = app(CreateQuoteDraft::class)->handle($company, $owner, (string) Str::uuid7());
        $outsider = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('quotes.current.show', [$other, $quote]))
            ->assertNotFound();
        $this->actingAs($owner)
            ->get(route('quotes.current.pdf', [$other, $quote]))
            ->assertNotFound();
        $this->actingAs($outsider)
            ->get(route('quotes.current.show', [$company, $quote]))
            ->assertNotFound();

        $this->tenant($other, fn () => $this->assertNull(Document::query()->find($quote->id)));
    }

    private function completeRomanianQuote(Document $document): void
    {
        $document->update([
            'issue_date' => '2026-08-26', 'currency_code' => 'RON', 'currency_precision' => 2,
            'document_language' => 'ro', 'customer_reference' => 'PO-ȘȚ-42',
            'terms_and_conditions' => 'Termeni cu ă â î ș ț.', 'notes' => 'Notă client.',
            'subtotal' => '180', 'tax_total' => '34.2', 'total' => '214.2',
        ]);
        Quote::query()->whereKey($document->id)->update([
            'validity_days' => 30, 'valid_until' => '2026-09-25',
        ]);
        DocumentCompanySnapshot::query()->where('document_id', $document->id)->update([
            'legal_name' => 'Compania Știință SRL', 'address_line_1' => 'Strada Întâi 1',
            'city' => 'București', 'country_code' => 'RO',
            'tax_registration_label' => 'CUI', 'tax_registration_identifier' => 'RO123456',
            'currency_display_style' => 'SYMBOL', 'primary_brand_color' => '#1E3A5F',
        ]);
        DocumentCustomerSnapshot::query()->create([
            'document_id' => $document->id, 'type' => 'COMPANY',
            'legal_name' => 'Client Știință SRL', 'city' => 'Cluj-Napoca', 'country_code' => 'RO',
        ]);
        DocumentBankSnapshot::query()->create([
            'document_id' => $document->id, 'label' => 'RON principal',
            'bank_name' => 'Banca Exemplu', 'account_holder' => 'Compania Știință SRL',
            'account_number' => 'RO49AAAA1B31007593840000', 'currency_code' => 'RON',
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

    private function company(User $owner, ?Account $account = null): Company
    {
        $company = app(CreateCompany::class)->handle($account ?? $this->account($owner), $owner, 'Quote Output SRL');
        $this->tenant($company, function (): void {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'Europe/Bucharest', 'default_document_language' => 'ro',
                'currency_display_style' => 'SYMBOL',
            ]);
            CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]);
        });

        return $company;
    }

    private function account(User $owner): Account
    {
        return Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
