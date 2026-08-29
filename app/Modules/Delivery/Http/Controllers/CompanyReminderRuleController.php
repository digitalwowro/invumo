<?php

namespace App\Modules\Delivery\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanySettingsNavigation;
use App\Modules\Delivery\Actions\SaveCompanyReminderRules;
use App\Modules\Delivery\Http\Requests\SaveReminderRulesRequest;
use App\Modules\Delivery\Queries\CompanyReminderRulesPage;
use App\Support\Inertia\CompaniesUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CompanyReminderRuleController extends Controller
{
    public function index(
        Request $request,
        Company $company,
        CompanyReminderRulesPage $page,
        CompanySettingsNavigation $navigation,
        CompaniesUiTranslationBag $translations,
    ): Response {
        return Inertia::render('companies/settings/reminders', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            ...$page->for($company, $request->user()),
            'companySettingsNavigation' => $navigation->for($company, $request->user())['items'],
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
        ]);
    }

    public function update(
        SaveReminderRulesRequest $request,
        Company $company,
        SaveCompanyReminderRules $save,
    ): RedirectResponse {
        $save->handle($company, $request->user(), $request->reminderRules());

        return back()->with('status', __('companies_ui.settings.reminders.feedback.saved'));
    }
}
