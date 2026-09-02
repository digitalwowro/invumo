<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Http\Requests\StoreCompanyRequest;
use App\Modules\Companies\Queries\AccessibleCompanies;
use App\Support\Inertia\CompaniesUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(
        Request $request,
        AccessibleCompanies $companies,
        CompaniesUiTranslationBag $translations,
    ): Response {
        $items = $companies->for($request->user())->map(fn ($membership): array => [
            'id' => $membership->company->id,
            'name' => $membership->company->name,
            'dashboardUrl' => route('companies.dashboard', $membership->company_id, false),
            'membersUrl' => null,
        ])->values();

        return Inertia::render('companies/index', [
            'companies' => $items,
            'translations' => $translations->toArray(),
        ]);
    }

    public function create(CompaniesUiTranslationBag $translations): Response
    {
        return Inertia::render('companies/create', [
            'indexUrl' => route('companies.index', absolute: false),
            'translations' => $translations->toArray(),
        ]);
    }

    public function store(
        StoreCompanyRequest $request,
        CreateCompany $createCompany,
    ): RedirectResponse {
        $user = $request->user();
        $account = $user->account()->firstOrFail();
        $company = $createCompany->handle($account, $user, $request->string('name')->toString());

        $request->session()->put('last_company_id', $company->id);

        return redirect()->route('companies.dashboard', $company);
    }
}
