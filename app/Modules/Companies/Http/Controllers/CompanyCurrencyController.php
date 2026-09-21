<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Actions\CreateCompanyCurrency;
use App\Modules\Companies\Actions\DeactivateCompanyCurrency;
use App\Modules\Companies\Actions\RestoreCompanyCurrency;
use App\Modules\Companies\Actions\SetDefaultCompanyCurrency;
use App\Modules\Companies\Actions\UpdateCompanyCurrencyPrecision;
use App\Modules\Companies\Exceptions\CompanyCurrencyException;
use App\Modules\Companies\Http\Requests\CreateCompanyCurrencyRequest;
use App\Modules\Companies\Http\Requests\UpdateCompanyCurrencyRequest;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyCurrenciesPage;
use App\Modules\Companies\Queries\CompanySettingsNavigation;
use App\Support\Inertia\CompaniesUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CompanyCurrencyController extends Controller
{
    public function index(
        Request $request,
        Company $company,
        CompanyCurrenciesPage $page,
        CompanySettingsNavigation $navigation,
        CompaniesUiTranslationBag $translations,
    ): Response {
        return Inertia::render('companies/settings/currencies', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            ...$page->for($company, $request->user()),
            'companySettingsNavigation' => $navigation->for($company, $request->user())['items'],
            'storeUrl' => route('company-currencies.store', $company, false),
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
        ]);
    }

    public function store(
        CreateCompanyCurrencyRequest $request,
        Company $company,
        CreateCompanyCurrency $create,
    ): RedirectResponse {
        try {
            $create->handle($company, $request->user(), $request->currency());
        } catch (CompanyCurrencyException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('companies_ui.settings.currencies.feedback.created'));
    }

    public function update(
        UpdateCompanyCurrencyRequest $request,
        Company $company,
        string $currency,
        UpdateCompanyCurrencyPrecision $update,
    ): RedirectResponse {
        try {
            $update->handle($company, $request->user(), $currency, $request->precision());
        } catch (CompanyCurrencyException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('companies_ui.settings.currencies.feedback.updated'));
    }

    public function setDefault(
        Request $request,
        Company $company,
        string $currency,
        SetDefaultCompanyCurrency $setDefault,
    ): RedirectResponse {
        try {
            $setDefault->handle($company, $request->user(), $currency);
        } catch (CompanyCurrencyException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('companies_ui.settings.currencies.feedback.defaulted'));
    }

    public function deactivate(
        Request $request,
        Company $company,
        string $currency,
        DeactivateCompanyCurrency $deactivate,
    ): RedirectResponse {
        try {
            $deactivate->handle($company, $request->user(), $currency);
        } catch (CompanyCurrencyException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('companies_ui.settings.currencies.feedback.deactivated'));
    }

    public function restore(
        Request $request,
        Company $company,
        string $currency,
        RestoreCompanyCurrency $restore,
    ): RedirectResponse {
        try {
            $restore->handle($company, $request->user(), $currency);
        } catch (CompanyCurrencyException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('companies_ui.settings.currencies.feedback.restored'));
    }

    private function validationError(CompanyCurrencyException $exception): never
    {
        throw ValidationException::withMessages([
            $exception->validationField() => __(
                "companies_ui.settings.currencies.errors.{$exception->reason()}",
            ),
        ]);
    }
}
