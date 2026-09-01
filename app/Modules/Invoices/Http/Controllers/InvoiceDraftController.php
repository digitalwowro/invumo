<?php

namespace App\Modules\Invoices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Documents\Data\DocumentDraftFailure;
use App\Modules\Documents\Data\DocumentLineFailure;
use App\Modules\Documents\Data\DocumentNumberAllocationException;
use App\Modules\Documents\Data\DocumentSourceFailure;
use App\Modules\Documents\Queries\DocumentDraftCreationPage;
use App\Modules\Invoices\Actions\CreateInvoiceDraft;
use App\Modules\Invoices\Actions\UpdateInvoiceDraft;
use App\Modules\Invoices\Exceptions\InvoiceDraftException;
use App\Modules\Invoices\Exceptions\InvoiceLifecycleException;
use App\Modules\Invoices\Http\Requests\CreateInvoiceDraftRequest;
use App\Modules\Invoices\Http\Requests\UpdateInvoiceDraftRequest;
use App\Modules\Invoices\Queries\InvoiceDraftPage;
use App\Support\Inertia\CatalogUiTranslationBag;
use App\Support\Inertia\CustomersUiTranslationBag;
use App\Support\Inertia\DocumentDeliveryTranslationBag;
use App\Support\Inertia\InvoicesUiTranslationBag;
use App\Support\Inertia\PublicDocumentsTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class InvoiceDraftController extends Controller
{
    public function create(
        Request $request,
        Company $company,
        DocumentDraftCreationPage $page,
        InvoicesUiTranslationBag $translations,
        CustomersUiTranslationBag $customerTranslations,
        CatalogUiTranslationBag $catalogTranslations,
    ): Response {
        return Inertia::render('invoices/create', [
            ...$page->invoice($company, $request->user(), app()->getLocale()),
            'translations' => $translations->toArray(),
            'customerTranslations' => $customerTranslations->toArray(),
            'catalogTranslations' => $catalogTranslations->toArray(),
        ]);
    }

    public function store(
        CreateInvoiceDraftRequest $request,
        Company $company,
        CreateInvoiceDraft $create,
    ): RedirectResponse {
        try {
            $invoice = $create->handle(
                $company,
                $request->user(),
                $request->creationKey(),
                $request->draft(),
            );
        } catch (InvoiceDraftException|DocumentDraftFailure|InvoiceLifecycleException|DocumentNumberAllocationException|DocumentSourceFailure|DocumentLineFailure $exception) {
            $field = match ($exception->reason()) {
                'customer_confirmation_required', 'customer_defaults_changed' => 'customer_id',
                'currency_unavailable', 'invoice_currency_locked_by_transactions' => 'currency_code',
                'bank_unavailable' => 'bank_account_id',
                'invoice_total_below_net_paid', 'issue_incomplete' => 'invoice',
                'details_invalid' => 'due_date',
                default => 'invoice',
            };

            throw ValidationException::withMessages([
                $field => __("invoices_ui.errors.{$exception->reason()}"),
            ]);
        }

        return redirect()
            ->route('invoices.edit', [$company, $invoice])
            ->with('status', __('invoices_ui.feedback.saved'));
    }

    public function edit(
        Request $request,
        Company $company,
        string $invoice,
        InvoiceDraftPage $page,
        InvoicesUiTranslationBag $translations,
        CustomersUiTranslationBag $customerTranslations,
        CatalogUiTranslationBag $catalogTranslations,
        PublicDocumentsTranslationBag $publicDocumentTranslations,
        DocumentDeliveryTranslationBag $deliveryTranslations,
    ): Response {
        return Inertia::render('invoices/edit', [
            ...$page->edit(
                $company,
                $request->user(),
                $invoice,
                app()->getLocale(),
                $request->session()->pull('inline_customer_id'),
                $request->session()->pull('inline_product_id'),
            ),
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
            'customerTranslations' => $customerTranslations->toArray(),
            'catalogTranslations' => $catalogTranslations->toArray(),
            'publicDocumentTranslations' => $publicDocumentTranslations->toArray(),
            'deliveryTranslations' => $deliveryTranslations->toArray(),
        ]);
    }

    public function update(
        UpdateInvoiceDraftRequest $request,
        Company $company,
        string $invoice,
        UpdateInvoiceDraft $update,
    ): RedirectResponse {
        try {
            $update->handle($company, $request->user(), $invoice, $request->draft());
        } catch (InvoiceDraftException|DocumentDraftFailure|InvoiceLifecycleException|DocumentSourceFailure|DocumentLineFailure $exception) {
            $field = match ($exception->reason()) {
                'stale' => 'edit_version',
                'customer_confirmation_required', 'customer_defaults_changed' => 'customer_id',
                'currency_unavailable' => 'currency_code',
                'invoice_currency_locked_by_transactions' => 'currency_code',
                'bank_unavailable' => 'bank_account_id',
                'invoice_total_below_net_paid' => 'invoice',
                'details_invalid' => 'due_date',
                'issue_incomplete' => 'invoice',
                default => 'lines',
            };

            throw ValidationException::withMessages([
                $field => __("invoices_ui.errors.{$exception->reason()}"),
            ]);
        }

        return back()->with('status', __('invoices_ui.feedback.saved'));
    }
}
