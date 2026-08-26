<?php

namespace App\Modules\Quotes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Documents\Data\DocumentNumberAllocationException;
use App\Modules\Quotes\Actions\ConvertQuoteToInvoice;
use App\Modules\Quotes\Actions\UnlinkQuoteInvoice;
use App\Modules\Quotes\Exceptions\QuoteConversionException;
use App\Modules\Quotes\Exceptions\QuoteInvoiceUnlinkException;
use App\Modules\Quotes\Http\Requests\ConvertQuoteRequest;
use App\Modules\Quotes\Http\Requests\UnlinkQuoteInvoiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class QuoteInvoiceController extends Controller
{
    public function store(
        ConvertQuoteRequest $request,
        Company $company,
        string $quote,
        ConvertQuoteToInvoice $convert,
    ): RedirectResponse {
        try {
            $invoice = $convert->handle($company, $request->user(), $quote, $request->conversion());
        } catch (QuoteConversionException|DocumentNumberAllocationException $exception) {
            throw ValidationException::withMessages([
                'conversion' => __("quotes_ui.errors.{$exception->reason()}"),
            ]);
        }

        return redirect()->route('invoices.edit', [$company, $invoice])
            ->with('status', __('quotes_ui.feedback.invoice_created'));
    }

    public function unlink(
        UnlinkQuoteInvoiceRequest $request,
        Company $company,
        string $quote,
        string $invoice,
        UnlinkQuoteInvoice $unlink,
    ): RedirectResponse {
        try {
            $unlink->handle($company, $request->user(), $quote, $invoice, $request->unlinking());
        } catch (QuoteInvoiceUnlinkException $exception) {
            throw ValidationException::withMessages([
                'unlink' => __("quotes_ui.errors.{$exception->reason()}"),
            ]);
        }

        return back()->with('status', __('quotes_ui.feedback.invoice_unlinked'));
    }
}
