<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Actions\UpdateCompanyAppearance;
use App\Modules\Companies\Http\Requests\UpdateCompanyAppearanceRequest;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAppearancePage;
use App\Modules\Companies\Queries\CompanyLogoResponse;
use App\Modules\Companies\Queries\CompanySettingsNavigation;
use App\Support\Inertia\CompaniesUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CompanyAppearanceController extends Controller
{
    public function edit(
        Request $request,
        Company $company,
        CompanyAppearancePage $page,
        CompanySettingsNavigation $navigation,
        CompaniesUiTranslationBag $translations,
    ): Response {
        return Inertia::render('companies/settings/appearance', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            ...$page->for($company, $request->user()),
            'companySettingsNavigation' => $navigation->for($company, $request->user())['items'],
            'updateUrl' => route('company-appearance.update', $company, false),
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
        ]);
    }

    public function update(
        UpdateCompanyAppearanceRequest $request,
        Company $company,
        UpdateCompanyAppearance $update,
    ): RedirectResponse {
        $update->handle($company, $request->user(), $request->appearance());

        return back()->with('status', __('companies_ui.settings.appearance.feedback.saved'));
    }

    public function logo(
        Request $request,
        Company $company,
        CompanyLogoResponse $response,
    ): StreamedResponse {
        return $response->for($company, $request->user());
    }
}
