<?php

use App\Foundation\Tenancy\TenantContext;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Actions\CreatePublicDocumentLink;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Invoices\Actions\CreateInvoiceDraft;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Transactions\Models\InvoiceTransaction;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

require_once __DIR__.'/Support/InvoiceBrowser.php';

afterEach(function (): void {
    Company::query()->pluck('id')->each(function (string $companyId): void {
        app(TenantContext::class)->runAsSystem(
            $companyId,
            function (): void {
                DB::connection(config('database.tenant_connection'))->transaction(function (): void {
                    EmailDelivery::query()
                        ->whereIn('dispatch_state', [
                            EmailDeliveryState::Queued,
                            EmailDeliveryState::Retrying,
                        ])
                        ->update([
                            'dispatch_state' => EmailDeliveryState::Rejected,
                            'failure_category' => 'test_cleanup',
                            'failure_summary' => 'Browser test cleanup.',
                            'failed_at' => now(),
                        ]);
                    Invoice::query()->where('lifecycle', InvoiceLifecycle::Cancelled)
                        ->update(['lifecycle' => InvoiceLifecycle::Issued]);
                    DB::statement('SET CONSTRAINTS invoice_transaction_ledger_trigger DEFERRED');
                    InvoiceTransaction::query()->delete();
                    Invoice::query()->where('lifecycle', InvoiceLifecycle::Issued)
                        ->update(['lifecycle' => InvoiceLifecycle::Draft]);
                });
            },
        );
    });
});

it('creates calculates and renders an Invoice Draft without viewport overflow', function () {
    [$owner, $company] = companyForInvoiceBrowser();

    $page = openInvoiceCreate($owner, $company)
        ->click('New invoice')
        ->assertSee('New invoice')
        ->assertValue('@invoice-payment-term-days', '30')
        ->type('Reference / PO', 'CLEAR ME')->click('@clear-document-draft')
        ->assertSee('Clear this draft?')->click('@confirm-document-reset')
        ->assertValue('Reference / PO', '')
        ->click('@document-customer-select')
        ->type('Customer search', 'Browser Invoice Customer')
        ->click('@document-customer-search')
        ->click('@document-customer-result')
        ->click('@document-customer-confirm')
        ->assertSee('Browser Invoice Customer SRL')
        ->click('Add product or service')
        ->type('@document-line-product-service-0', 'Browser invoice')
        ->wait(0.3)->click('@document-product-result')
        ->assertValue('Item price', '100.00')
        ->type('Quantity', '2')
        ->type('Reference / PO', 'PO-INVOICE-42')
        ->assertSee('238.00')
        ->click('Save invoice')
        ->assertSee('Invoice saved.')->assertSee('I-'.now('Europe/Bucharest')->year.'-0001')
        ->type('Reference / PO', 'DISCARD ME')->click('@discard-document-changes')
        ->assertSee('Discard unsaved changes?')->click('@confirm-document-reset')
        ->assertValue('Reference / PO', 'PO-INVOICE-42')
        ->assertScript("document.querySelector('[data-testid=invoice-issue-trigger]')?.disabled === false")
        ->click('@invoice-issue-trigger')->assertSee('Issue this invoice?')
        ->click('@invoice-issue-confirm')->assertSee('Invoice issued.')
        ->assertSee('Issued');
    $page->script('window.scrollTo(0, 0)');
    $page
        ->resize(1440, 817)
        ->wait(0.2)
        ->assertScript("Array.from(document.querySelectorAll('button[data-variant=money]')).some((button) => button.textContent?.includes('Record payment'))")
        ->screenshot(false, 'implementation-invoice-current-refined.png')
        ->click('@invoice-workspace-money-tab')
        ->click('Record payment')
        ->type('@transaction-amount', '100')
        ->click('Save transaction')
        ->assertSee('Transaction recorded.')->assertSee('Partial')
        ->assertSee('138.00 RON')
        ->click('Send receipt')
        ->assertSee('Send a payment-received email?')
        ->assertSee('Recording a Payment never sends it automatically.')
        ->click('Queue receipt email')
        ->assertSee('Payment-received email queued.')
        ->assertSee('Queued')
        ->wait(0.2)
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->assertScript("document.querySelector('[data-testid=pdf-download]')?.tagName === 'A'")->assertScript("document.querySelector('[data-testid=pdf-download]')?.hasAttribute('download') === true")
        ->click('View')
        ->assertSee('Current invoice')->assertSee('PO-INVOICE-42')->assertSee('238.00')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->navigate(route('invoices.index', $company, false))
        ->assertSee('PO-INVOICE-42')->assertSee('Partial')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->click('Transactions')
        ->assertSee('Company’s recorded Payments')
        ->assertSee('RON 100.00')
        ->assertSee('Browser Invoice Customer SRL')
        ->click('Open Invoice')->assertScript("document.querySelector('[data-testid=invoice-workspace-money-tab]')?.getAttribute('data-state') === 'active'")->assertSee('Record payment')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('keeps document-default and line tax choices independent in the wide editor', function () {
    [$owner, $company] = companyForInvoiceBrowser();

    $page = openInvoiceCreate($owner, $company)
        ->resize(1440, 1100)
        ->click('New invoice')
        ->click('Add product or service')
        ->assertScript("document.querySelector('[data-slot=table][aria-label=\"Products & Services\"]') !== null")
        ->assertDontSee('Edit line')
        ->type('@document-line-product-service-0', 'Browser tax behavior')
        ->type('Description', 'Tax inheritance verification')
        ->type('Item price', '100')
        ->type('Quantity', '2')
        ->assertSee('Gross')->assertSee('Discount')->assertSee('Taxable base')->assertSee('Paid')->assertSee('Outstanding')
        ->assertSee('238.00')
        ->click('@document-tax-default')
        ->assertScript("Array.from(document.querySelectorAll('[role=option]')).some((option) => option.textContent?.includes('Reduced VAT 9%'))");
    $page->script("Array.from(document.querySelectorAll('[role=option]')).find((option) => option.textContent?.includes('Reduced VAT 9%'))?.click()");
    $page
        ->assertSee('218.00')
        ->click('@document-line-tax-0')
        ->assertScript("Array.from(document.querySelectorAll('[role=option]')).some((option) => option.textContent?.includes('TVA 19%'))")
        ->assertScript("Array.from(document.querySelectorAll('[role=option]')).every((option) => !option.textContent?.includes('Document default'))")
        ->assertScript("Array.from(document.querySelectorAll('[role=option]')).filter((option) => option.textContent?.includes('Reduced VAT 9%')).length === 1");
    $page->script("Array.from(document.querySelectorAll('[role=option]')).find((option) => option.textContent?.includes('TVA 19%'))?.click()");
    $page
        ->assertSee('238.00')
        ->click('@document-tax-default')
        ->assertScript("Array.from(document.querySelectorAll('[role=option]')).some((option) => option.textContent?.includes('No tax preset'))");
    $page->script("Array.from(document.querySelectorAll('[role=option]')).find((option) => option.textContent?.includes('No tax preset'))?.click()");
    $page
        ->assertSee('238.00')
        ->click('@document-tax-default')
        ->assertScript("Array.from(document.querySelectorAll('[role=option]')).some((option) => option.textContent?.includes('Reduced VAT 9%'))");
    $page->script("Array.from(document.querySelectorAll('[role=option]')).find((option) => option.textContent?.includes('Reduced VAT 9%'))?.click()");
    $page
        ->click('@document-line-tax-0')
        ->assertScript("Array.from(document.querySelectorAll('[role=option]')).every((option) => !option.textContent?.includes('Document default'))")
        ->assertScript("Array.from(document.querySelectorAll('[role=option]')).filter((option) => option.textContent?.includes('Reduced VAT 9%')).length === 1");
    $page->script("Array.from(document.querySelectorAll('[role=option]')).find((option) => option.textContent?.includes('Reduced VAT 9%'))?.click()");
    $page
        ->assertSee('218.00')
        ->assertDontSee('Updates lines still using the document default; line overrides stay unchanged.')
        ->assertDontSee('Bank account:')
        ->wait(0.3)
        ->assertScript("(() => { const table = document.querySelector('[data-slot=table-container][aria-label=\"Products & Services\"]'); return table !== null && table.scrollWidth === table.clientWidth && table.scrollLeft === 0; })()")
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    $page->script('window.scrollTo(0, 0)');
    $page->screenshot(false, 'implementation-build-refined.png');
});

it('keeps the Romanian Invoice Draft and current view usable on mobile', function () {
    [$owner, $company] = companyForInvoiceBrowser('ro');

    openInvoiceCreate($owner, $company, mobile: true)
        ->click('Factură nouă')
        ->assertSee('Factură nouă')
        ->click('@document-customer-select')
        ->type('Căutare client', 'Browser Invoice Customer')
        ->click('@document-customer-search')
        ->click('@document-customer-result')
        ->click('@document-customer-confirm')
        ->click('Adaugă produs sau serviciu')
        ->assertSee('Data scadenței')
        ->type('@document-line-product-service-0', 'Consultanță')
        ->type('Preț unitar', '100')
        ->type('Cantitate', '1')
        ->screenshot(false, 'implementation-mobile-line.png')
        ->click('Salvează factura')
        ->assertSee('Factura a fost salvată.')
        ->assertSee('I-'.now('Europe/Bucharest')->year.'-0001')
        ->click('@invoice-issue-trigger')
        ->click('@invoice-issue-confirm')
        ->assertSee('Factura a fost emisă.')
        ->click('@invoice-workspace-money-tab')
        ->click('Înregistrează plata')
        ->type('@transaction-amount', '40')
        ->click('Salvează tranzacția')
        ->assertSee('Tranzacția a fost înregistrată.')
        ->assertSee('Parțial')
        ->assertSee('79.00 RON')
        ->wait(0.2)
        ->click('Vizualizează')
        ->assertSee('Factura curentă')
        ->assertSee('Invoice Browser SRL')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->navigate(route('invoices.index', $company, false))
        ->assertSee('Facturi')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues()
        ->navigate(route('transactions.index', $company, false))
        ->assertSee('Tranzacții')
        ->assertSee('RON 40.00')
        ->assertSee('Browser Invoice Customer SRL')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('renders a Romanian public Invoice link on a narrow viewport', function () {
    [$owner, $company] = companyForInvoiceBrowser('ro');
    $invoice = app(CreateInvoiceDraft::class)->handle($company, $owner, (string) Str::uuid7());
    $link = app(CreatePublicDocumentLink::class)->handle(
        $company,
        $owner,
        $invoice->id,
        DocumentKind::Invoice,
    );

    visit(route('public-invoices.show', $link->token_ciphertext, false))
        ->on()
        ->iPhone15()
        ->assertSee('Factură '.$invoice->rendered_number)
        ->assertSee('Descarcă PDF-ul')
        ->assertSee('Partajat securizat cu Invumo')
        ->assertScript("document.querySelector('[data-testid=public-pdf-download]')?.tagName === 'A'")
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('cancels and reopens an Invoice with localized mobile controls', function () {
    [$owner, $company] = companyForInvoiceBrowser('ro');

    openInvoiceCreate($owner, $company, mobile: true)
        ->assertSee('Factură nouă')
        ->click('Creează ciorna facturii')
        ->assertSee('Adaugă linie')
        ->click('@document-customer-select')
        ->type('Căutare client', 'Browser Invoice Customer')
        ->click('@document-customer-search')
        ->click('@document-customer-result')
        ->click('@document-customer-confirm')
        ->assertSee('Browser Invoice Customer SRL')
        ->click('Adaugă linie')
        ->type('Preț unitar', '100')
        ->type('Cantitate', '1')
        ->click('Salvează factura')
        ->assertSee('Factura a fost salvată.')
        ->click('@invoice-issue-trigger')
        ->click('@invoice-issue-confirm')
        ->assertSee('Factura a fost emisă.')
        ->assertScript("document.querySelector('[data-testid=invoice-cancel-trigger]') !== null")
        ->assertScript("document.querySelector('[data-testid=invoice-cancel-trigger]')?.disabled === false")
        ->click('@invoice-cancel-trigger')
        ->assertSee('Pregătită pentru anulare')
        ->type('Motivul anulării', 'Factura a fost emisă din greșeală')
        ->check('Confirm că această Factură trebuie anulată.')
        ->click('@invoice-cancel-confirm')
        ->assertSee('Factura a fost anulată.')
        ->assertSee('Anulată')
        ->click('@invoice-workspace-money-tab')
        ->assertSee('istoric protejat la modificare')
        ->click('@invoice-reopen-trigger')
        ->type('Motivul redeschiderii', 'Reluarea colectării')
        ->check('Confirm că această Factură trebuie să revină în starea Emisă.')
        ->click('@invoice-reopen-confirm')
        ->assertSee('Factura a fost redeschisă.')
        ->assertSee('Emisă')
        ->click('Șterge')
        ->assertSee('Ștergi definitiv această Factură?')
        ->assertScript("document.querySelector('[data-testid=destructive-action-confirm]')?.disabled === true")
        ->type('Numărul exact al Facturii', 'I-'.now('Europe/Bucharest')->year.'-0001')
        ->check('Înțeleg că această Factură va fi ștearsă definitiv.')
        ->click('@destructive-action-confirm')
        ->assertSee('Factura a fost ștearsă definitiv.')
        ->assertSee('Facturi')
        ->assertScript('document.documentElement.scrollWidth === document.documentElement.clientWidth')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});
