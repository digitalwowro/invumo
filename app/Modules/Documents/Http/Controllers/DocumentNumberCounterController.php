<?php

namespace App\Modules\Documents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Documents\Actions\RealignQuoteNumberCounter;
use App\Modules\Documents\Exceptions\NumberCounterException;
use App\Modules\Documents\Http\Requests\RealignNumberCounterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class DocumentNumberCounterController extends Controller
{
    public function update(
        RealignNumberCounterRequest $request,
        Company $company,
        string $counter,
        RealignQuoteNumberCounter $realign,
    ): RedirectResponse {
        try {
            $realign->handle($company, $request->user(), $counter, $request->realignment());
        } catch (NumberCounterException $exception) {
            throw ValidationException::withMessages([
                'next_value' => __("companies_ui.settings.numbering.errors.{$exception->reason()}"),
            ]);
        }

        return back()->with('status', __('companies_ui.settings.numbering.feedback.counter_saved'));
    }
}
