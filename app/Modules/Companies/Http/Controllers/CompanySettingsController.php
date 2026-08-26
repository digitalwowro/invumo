<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Actions\UpdateCompanyConfiguration;
use App\Modules\Companies\Exceptions\CompanyConfigurationException;
use App\Modules\Companies\Http\Requests\UpdateCompanyConfigurationRequest;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyConfigurationPage;
use App\Modules\Companies\Queries\CompanySettingsNavigation;
use App\Support\Inertia\CompaniesUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CompanySettingsController extends Controller
{
    public function index(
        Request $request,
        Company $company,
        CompanySettingsNavigation $navigation,
    ): RedirectResponse {
        $settingsNavigation = $navigation->for($company, $request->user());

        return redirect()->to($settingsNavigation['firstUrl']);
    }

    public function edit(
        Request $request,
        Company $company,
        CompanyConfigurationPage $page,
        CompanySettingsNavigation $navigation,
        CompaniesUiTranslationBag $translations,
    ): Response {
        return Inertia::render('companies/settings/profile', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            ...$page->for($company, $request->user(), app()->getLocale()),
            'companySettingsNavigation' => $navigation->for($company, $request->user())['items'],
            'updateUrl' => route('company-settings.profile.update', $company, false),
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
        ]);
    }

    public function update(
        UpdateCompanyConfigurationRequest $request,
        Company $company,
        UpdateCompanyConfiguration $update,
    ): RedirectResponse {
        try {
            $update->handle($company, $request->user(), $request->configuration());
        } catch (CompanyConfigurationException $exception) {
            throw ValidationException::withMessages([
                $exception->validationField() => __(
                    "companies_ui.settings.profile.errors.{$exception->reason()}",
                ),
            ]);
        }

        return back()->with('status', __('companies_ui.settings.profile.feedback.saved'));
    }
}
