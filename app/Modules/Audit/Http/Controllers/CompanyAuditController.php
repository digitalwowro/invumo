<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Http\Requests\CompanyAuditListRequest;
use App\Modules\Audit\Queries\CompanyAuditPage;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanySettingsNavigation;
use App\Support\Inertia\CompaniesUiTranslationBag;
use Inertia\Inertia;
use Inertia\Response;

final class CompanyAuditController extends Controller
{
    public function __invoke(
        CompanyAuditListRequest $request,
        Company $company,
        CompanyAuditPage $page,
        CompanySettingsNavigation $navigation,
        CompaniesUiTranslationBag $translations,
    ): Response {
        return Inertia::render('companies/settings/audit', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            ...$page->for($company, $request->user(), $request),
            'companySettingsNavigation' => $navigation->for($company, $request->user())['items'],
            'translations' => $translations->toArray(),
        ]);
    }
}
