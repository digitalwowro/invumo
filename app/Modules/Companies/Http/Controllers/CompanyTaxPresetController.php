<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Actions\ArchiveTaxPreset;
use App\Modules\Companies\Actions\CreateTaxPreset;
use App\Modules\Companies\Actions\DeleteTaxPreset;
use App\Modules\Companies\Actions\RestoreTaxPreset;
use App\Modules\Companies\Actions\UpdateTaxPreset;
use App\Modules\Companies\Exceptions\TaxPresetException;
use App\Modules\Companies\Http\Requests\SaveTaxPresetRequest;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanySettingsNavigation;
use App\Modules\Companies\Queries\CompanyTaxPresetsPage;
use App\Support\Inertia\CompaniesUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CompanyTaxPresetController extends Controller
{
    public function index(
        Request $request,
        Company $company,
        CompanyTaxPresetsPage $page,
        CompanySettingsNavigation $navigation,
        CompaniesUiTranslationBag $translations,
    ): Response {
        return Inertia::render('companies/settings/taxes', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            ...$page->for($company, $request->user()),
            'companySettingsNavigation' => $navigation->for($company, $request->user())['items'],
            'storeUrl' => route('company-tax-presets.store', $company, false),
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
        ]);
    }

    public function store(
        SaveTaxPresetRequest $request,
        Company $company,
        CreateTaxPreset $create,
    ): RedirectResponse {
        $create->handle($company, $request->user(), $request->preset());

        return back()->with('status', __('companies_ui.settings.taxes.feedback.created'));
    }

    public function update(
        SaveTaxPresetRequest $request,
        Company $company,
        string $taxPreset,
        UpdateTaxPreset $update,
    ): RedirectResponse {
        try {
            $update->handle($company, $request->user(), $taxPreset, $request->preset());
        } catch (TaxPresetException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('companies_ui.settings.taxes.feedback.updated'));
    }

    public function archive(
        Request $request,
        Company $company,
        string $taxPreset,
        ArchiveTaxPreset $archive,
    ): RedirectResponse {
        try {
            $archive->handle($company, $request->user(), $taxPreset);
        } catch (TaxPresetException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('companies_ui.settings.taxes.feedback.archived'));
    }

    public function restore(
        Request $request,
        Company $company,
        string $taxPreset,
        RestoreTaxPreset $restore,
    ): RedirectResponse {
        try {
            $restore->handle($company, $request->user(), $taxPreset);
        } catch (TaxPresetException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('companies_ui.settings.taxes.feedback.restored'));
    }

    public function destroy(
        Request $request,
        Company $company,
        string $taxPreset,
        DeleteTaxPreset $delete,
    ): RedirectResponse {
        try {
            $delete->handle($company, $request->user(), $taxPreset);
        } catch (TaxPresetException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('companies_ui.settings.taxes.feedback.deleted'));
    }

    private function validationError(TaxPresetException $exception): never
    {
        throw ValidationException::withMessages([
            'tax_preset' => __("companies_ui.settings.taxes.errors.{$exception->reason()}"),
        ]);
    }
}
