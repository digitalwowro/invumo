<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Actions\ArchiveBankAccount;
use App\Modules\Companies\Actions\CreateBankAccount;
use App\Modules\Companies\Actions\DeleteBankAccount;
use App\Modules\Companies\Actions\RestoreBankAccount;
use App\Modules\Companies\Actions\UpdateBankAccount;
use App\Modules\Companies\Exceptions\BankAccountException;
use App\Modules\Companies\Http\Requests\SaveBankAccountRequest;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyBankAccountsPage;
use App\Modules\Companies\Queries\CompanySettingsNavigation;
use App\Support\Inertia\CompaniesUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CompanyBankAccountController extends Controller
{
    public function index(
        Request $request,
        Company $company,
        CompanyBankAccountsPage $page,
        CompanySettingsNavigation $navigation,
        CompaniesUiTranslationBag $translations,
    ): Response {
        return Inertia::render('companies/settings/bank-accounts', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            ...$page->for($company, $request->user()),
            'companySettingsNavigation' => $navigation->for($company, $request->user())['items'],
            'storeUrl' => route('company-bank-accounts.store', $company, false),
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
        ]);
    }

    public function store(
        SaveBankAccountRequest $request,
        Company $company,
        CreateBankAccount $create,
    ): RedirectResponse {
        try {
            $create->handle($company, $request->user(), $request->account());
        } catch (BankAccountException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('companies_ui.settings.bank_accounts.feedback.created'));
    }

    public function update(
        SaveBankAccountRequest $request,
        Company $company,
        string $bankAccount,
        UpdateBankAccount $update,
    ): RedirectResponse {
        try {
            $update->handle($company, $request->user(), $bankAccount, $request->account());
        } catch (BankAccountException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('companies_ui.settings.bank_accounts.feedback.updated'));
    }

    public function archive(
        Request $request,
        Company $company,
        string $bankAccount,
        ArchiveBankAccount $archive,
    ): RedirectResponse {
        try {
            $archive->handle($company, $request->user(), $bankAccount);
        } catch (BankAccountException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('companies_ui.settings.bank_accounts.feedback.archived'));
    }

    public function restore(
        Request $request,
        Company $company,
        string $bankAccount,
        RestoreBankAccount $restore,
    ): RedirectResponse {
        try {
            $restore->handle($company, $request->user(), $bankAccount);
        } catch (BankAccountException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('companies_ui.settings.bank_accounts.feedback.restored'));
    }

    public function destroy(
        Request $request,
        Company $company,
        string $bankAccount,
        DeleteBankAccount $delete,
    ): RedirectResponse {
        try {
            $delete->handle($company, $request->user(), $bankAccount);
        } catch (BankAccountException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('companies_ui.settings.bank_accounts.feedback.deleted'));
    }

    private function validationError(BankAccountException $exception): never
    {
        throw ValidationException::withMessages([
            'bank_account' => __("companies_ui.settings.bank_accounts.errors.{$exception->reason()}"),
        ]);
    }
}
