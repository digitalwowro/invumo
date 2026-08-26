<?php

namespace App\Modules\Quotes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Quotes\Actions\DeleteQuote;
use App\Modules\Quotes\Exceptions\QuoteDeletionException;
use App\Modules\Quotes\Http\Requests\DeleteQuoteRequest;
use App\Modules\Quotes\Http\Requests\QuoteListRequest;
use App\Modules\Quotes\Queries\QuoteListPage;
use App\Support\Inertia\QuotesUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class QuoteController extends Controller
{
    public function index(
        QuoteListRequest $request,
        Company $company,
        QuoteListPage $page,
        QuotesUiTranslationBag $translations,
    ): Response {
        return Inertia::render('quotes/index', [
            ...$page->for($company, $request->user(), $request),
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
        ]);
    }

    public function destroy(
        DeleteQuoteRequest $request,
        Company $company,
        string $quote,
        DeleteQuote $delete,
    ): RedirectResponse {
        try {
            $delete->handle($company, $request->user(), $quote, $request->deletion());
        } catch (QuoteDeletionException $exception) {
            throw ValidationException::withMessages([
                'quote' => __("quotes_ui.errors.{$exception->reason()}"),
            ]);
        }

        return redirect()->route('quotes.index', $company)
            ->with('status', __('quotes_ui.feedback.deleted'));
    }
}
