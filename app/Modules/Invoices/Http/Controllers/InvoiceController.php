<?php

namespace App\Modules\Invoices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Invoices\Actions\DeleteInvoice;
use App\Modules\Invoices\Exceptions\InvoiceDeletionException;
use App\Modules\Invoices\Http\Requests\DeleteInvoiceRequest;
use App\Modules\Invoices\Http\Requests\InvoiceListRequest;
use App\Modules\Invoices\Queries\InvoiceListPage;
use App\Support\Inertia\InvoicesUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class InvoiceController extends Controller
{
    public function index(
        InvoiceListRequest $request,
        Company $company,
        InvoiceListPage $page,
        InvoicesUiTranslationBag $translations,
    ): Response {
        return Inertia::render('invoices/index', [
            ...$page->for($company, $request->user(), $request),
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
        ]);
    }

    public function destroy(
        DeleteInvoiceRequest $request,
        Company $company,
        string $invoice,
        DeleteInvoice $delete,
    ): RedirectResponse {
        try {
            $delete->handle($company, $request->user(), $invoice, $request->deletion());
        } catch (InvoiceDeletionException $exception) {
            $field = $exception->reason() === 'delete_number_confirmation_invalid'
                ? 'confirmation_number'
                : 'invoice';

            throw ValidationException::withMessages([
                $field => __("invoices_ui.errors.{$exception->reason()}"),
            ]);
        }

        return redirect()->route('invoices.index', $company)
            ->with('status', __('invoices_ui.feedback.deleted'));
    }
}
