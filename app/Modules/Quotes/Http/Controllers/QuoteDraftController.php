<?php

namespace App\Modules\Quotes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Documents\Data\DocumentNumberAllocationException;
use App\Modules\Quotes\Actions\CreateQuoteDraft;
use App\Modules\Quotes\Actions\UpdateQuoteDraft;
use App\Modules\Quotes\Exceptions\QuoteDraftException;
use App\Modules\Quotes\Http\Requests\CreateQuoteDraftRequest;
use App\Modules\Quotes\Http\Requests\UpdateQuoteDraftRequest;
use App\Modules\Quotes\Queries\QuoteDraftPage;
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
        QuoteDraftPage $page,
        QuotesUiTranslationBag $translations,
    ): Response {
        return Inertia::render('quotes/create', [
            ...$page->create($company, $request->user()),
            'translations' => $translations->toArray(),
        ]);
    }

    public function store(
        CreateQuoteDraftRequest $request,
        Company $company,
        CreateQuoteDraft $create,
    ): RedirectResponse {
        try {
            $quote = $create->handle($company, $request->user(), $request->creationKey());
        } catch (QuoteDraftException|DocumentNumberAllocationException $exception) {
            throw ValidationException::withMessages([
                'quote' => __("quotes_ui.errors.{$exception->reason()}"),
            ]);
        }

        return redirect()->route('quotes.edit', [$company, $quote]);
    }

    public function edit(
        Request $request,
        Company $company,
        string $quote,
        QuoteDraftPage $page,
        QuotesUiTranslationBag $translations,
    ): Response {
        return Inertia::render('quotes/edit', [
            ...$page->edit($company, $request->user(), $quote),
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
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
        } catch (QuoteDraftException $exception) {
            $field = $exception->reason() === 'stale' ? 'edit_version' : 'lines';

            throw ValidationException::withMessages([
                $field => __("quotes_ui.errors.{$exception->reason()}"),
            ]);
        }

        return back()->with('status', __('quotes_ui.feedback.saved'));
    }
}
