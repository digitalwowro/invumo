<?php

namespace App\Modules\Quotes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Documents\Data\DocumentDraftFailure;
use App\Modules\Documents\Data\DocumentLineFailure;
use App\Modules\Documents\Data\DocumentNumberAllocationException;
use App\Modules\Documents\Data\DocumentSourceFailure;
use App\Modules\Documents\Queries\DocumentDraftCreationPage;
use App\Modules\Quotes\Actions\CreateQuoteDraft;
use App\Modules\Quotes\Actions\UpdateQuoteDraft;
use App\Modules\Quotes\Exceptions\QuoteDraftException;
use App\Modules\Quotes\Http\Requests\CreateQuoteDraftRequest;
use App\Modules\Quotes\Http\Requests\UpdateQuoteDraftRequest;
use App\Modules\Quotes\Queries\QuoteDraftPage;
use App\Support\Inertia\CatalogUiTranslationBag;
use App\Support\Inertia\CustomersUiTranslationBag;
use App\Support\Inertia\DocumentDeliveryTranslationBag;
use App\Support\Inertia\PublicDocumentsTranslationBag;
use App\Support\Inertia\QuotesUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class QuoteDraftController extends Controller
{
    public function create(
        Request $request,
        Company $company,
        DocumentDraftCreationPage $page,
        QuotesUiTranslationBag $translations,
        CustomersUiTranslationBag $customerTranslations,
        CatalogUiTranslationBag $catalogTranslations,
    ): Response {
        return Inertia::render('quotes/create', [
            ...$page->quote($company, $request->user(), app()->getLocale()),
            'translations' => $translations->toArray(),
            'customerTranslations' => $customerTranslations->toArray(),
            'catalogTranslations' => $catalogTranslations->toArray(),
        ]);
    }

    public function store(
        CreateQuoteDraftRequest $request,
        Company $company,
        CreateQuoteDraft $create,
    ): RedirectResponse {
        try {
            $quote = $create->handle(
                $company,
                $request->user(),
                $request->creationKey(),
                $request->draft(),
            );
        } catch (QuoteDraftException|DocumentDraftFailure|DocumentNumberAllocationException|DocumentSourceFailure|DocumentLineFailure $exception) {
            $field = match ($exception->reason()) {
                'customer_confirmation_required', 'customer_defaults_changed' => 'customer_id',
                'currency_unavailable', 'currency_linked' => 'currency_code',
                'bank_unavailable' => 'bank_account_id',
                'details_invalid' => 'valid_until',
                default => 'quote',
            };

            throw ValidationException::withMessages([
                $field => __("quotes_ui.errors.{$exception->reason()}"),
            ]);
        }

        return redirect()
            ->route('quotes.edit', [$company, $quote])
            ->with('status', __('quotes_ui.feedback.saved'));
    }

    public function edit(
        Request $request,
        Company $company,
        string $quote,
        QuoteDraftPage $page,
        QuotesUiTranslationBag $translations,
        CustomersUiTranslationBag $customerTranslations,
        CatalogUiTranslationBag $catalogTranslations,
        PublicDocumentsTranslationBag $publicDocumentTranslations,
        DocumentDeliveryTranslationBag $deliveryTranslations,
    ): Response {
        return Inertia::render('quotes/edit', [
            ...$page->edit(
                $company,
                $request->user(),
                $quote,
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
        UpdateQuoteDraftRequest $request,
        Company $company,
        string $quote,
        UpdateQuoteDraft $update,
    ): RedirectResponse {
        try {
            $update->handle($company, $request->user(), $quote, $request->draft());
        } catch (QuoteDraftException|DocumentDraftFailure|DocumentSourceFailure|DocumentLineFailure $exception) {
            $field = match ($exception->reason()) {
                'stale' => 'edit_version',
                'customer_confirmation_required', 'customer_defaults_changed' => 'customer_id',
                'currency_unavailable', 'currency_linked' => 'currency_code',
                'bank_unavailable' => 'bank_account_id',
                'details_invalid' => 'valid_until',
                default => 'lines',
            };

            throw ValidationException::withMessages([
                $field => __("quotes_ui.errors.{$exception->reason()}"),
            ]);
        }

        return back()->with('status', __('quotes_ui.feedback.saved'));
    }
}
