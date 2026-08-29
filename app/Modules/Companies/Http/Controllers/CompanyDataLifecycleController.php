<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Actions\EraseCompany;
use App\Modules\Companies\Exceptions\CompanyErasureException;
use App\Modules\Companies\Http\Requests\EraseCompanyRequest;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyErasurePage;
use App\Modules\Companies\Queries\CompanySettingsNavigation;
use App\Support\Inertia\CompaniesUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CompanyDataLifecycleController extends Controller
{
    public function show(
        Request $request,
        Company $company,
        CompanyErasurePage $page,
        CompanySettingsNavigation $navigation,
        CompaniesUiTranslationBag $translations,
    ): Response {
        return Inertia::render('companies/settings/data-lifecycle', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            ...$page->for($company, $request->user()),
            'companySettingsNavigation' => $navigation->for($company, $request->user())['items'],
            'translations' => $translations->toArray(),
        ]);
    }

    public function destroy(
        EraseCompanyRequest $request,
        Company $company,
        EraseCompany $erase,
    ): RedirectResponse {
        try {
            $erase->handle($company, $request->user(), $request->erasure());
        } catch (CompanyErasureException $exception) {
            $field = $exception->reason() === 'name_confirmation_invalid'
                ? 'confirmation_name'
                : 'company';

            throw ValidationException::withMessages([
                $field => __("companies_ui.settings.data_lifecycle.errors.{$exception->reason()}"),
            ]);
        }

        $request->session()->forget('last_company_id');
        $request->session()->put('company_context.skip_remember_once', true);
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('companies_ui.settings.data_lifecycle.feedback.erased'),
        ]);

        return redirect()->route('companies.index');
    }
}
