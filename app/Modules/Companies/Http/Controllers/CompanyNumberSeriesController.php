<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Actions\UpdateNumberSeriesConfiguration;
use App\Modules\Companies\Exceptions\NumberSeriesException;
use App\Modules\Companies\Http\Requests\UpdateNumberSeriesConfigurationRequest;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyNumberSeriesPage;
use App\Modules\Companies\Queries\CompanySettingsNavigation;
use App\Support\Inertia\CompaniesUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CompanyNumberSeriesController extends Controller
{
    public function edit(
        Request $request,
        Company $company,
        CompanyNumberSeriesPage $page,
        CompanySettingsNavigation $navigation,
        CompaniesUiTranslationBag $translations,
    ): Response {
        return Inertia::render('companies/settings/numbering', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            ...$page->for($company, $request->user()),
            'companySettingsNavigation' => $navigation->for($company, $request->user())['items'],
            'updateUrl' => route('company-number-series.update', $company, false),
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
        ]);
    }

    public function update(
        UpdateNumberSeriesConfigurationRequest $request,
        Company $company,
        UpdateNumberSeriesConfiguration $update,
    ): RedirectResponse {
        try {
            $update->handle($company, $request->user(), $request->configuration());
        } catch (NumberSeriesException $exception) {
            $message = __("companies_ui.settings.numbering.errors.{$exception->reason()}");

            throw ValidationException::withMessages(array_fill_keys(
                $exception->fields(),
                $message,
            ));
        }

        return back()->with('status', __('companies_ui.settings.numbering.feedback.saved'));
    }
}
