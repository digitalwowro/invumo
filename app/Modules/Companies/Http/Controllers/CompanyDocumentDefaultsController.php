<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Actions\UpdateCompanyDocumentDefaults;
use App\Modules\Companies\Http\Requests\UpdateCompanyDocumentDefaultsRequest;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyDocumentDefaultsPage;
use App\Modules\Companies\Queries\CompanySettingsNavigation;
use App\Support\Inertia\CompaniesUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CompanyDocumentDefaultsController extends Controller
{
    public function edit(
        Request $request,
        Company $company,
        CompanyDocumentDefaultsPage $page,
        CompanySettingsNavigation $navigation,
        CompaniesUiTranslationBag $translations,
    ): Response {
        return Inertia::render('companies/settings/documents', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            ...$page->for($company, $request->user()),
            'companySettingsNavigation' => $navigation->for($company, $request->user())['items'],
            'updateUrl' => route('company-document-defaults.update', $company, false),
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
        ]);
    }

    public function update(
        UpdateCompanyDocumentDefaultsRequest $request,
        Company $company,
        UpdateCompanyDocumentDefaults $update,
    ): RedirectResponse {
        $update->handle($company, $request->user(), $request->defaults());

        return back()->with('status', __('companies_ui.settings.documents.feedback.saved'));
    }
}
