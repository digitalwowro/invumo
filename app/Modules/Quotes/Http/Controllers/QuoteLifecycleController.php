<?php

namespace App\Modules\Quotes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Quotes\Actions\CorrectQuoteLifecycle;
use App\Modules\Quotes\Exceptions\QuoteLifecycleException;
use App\Modules\Quotes\Http\Requests\CorrectQuoteLifecycleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class QuoteLifecycleController extends Controller
{
    public function update(
        CorrectQuoteLifecycleRequest $request,
        Company $company,
        string $quote,
        CorrectQuoteLifecycle $correct,
    ): RedirectResponse {
        try {
            $correct->handle($company, $request->user(), $quote, $request->correction());
        } catch (QuoteLifecycleException $exception) {
            throw ValidationException::withMessages([
                'lifecycle' => __("quotes_ui.errors.{$exception->reason()}"),
            ]);
        }

        return back()->with('status', __('quotes_ui.feedback.lifecycle_corrected'));
    }
}
