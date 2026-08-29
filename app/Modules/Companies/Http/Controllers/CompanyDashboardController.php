<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyDashboardPage;
use App\Support\Inertia\DashboardTranslationBag;
use Inertia\Inertia;
use Inertia\Response;

class CompanyDashboardController extends Controller
{
    public function __invoke(
        Company $company,
        DashboardTranslationBag $translations,
        CompanyDashboardPage $page,
    ): Response {
        return Inertia::render('dashboard', [
            'company' => ['name' => $company->name],
            'dashboard' => $page->for($company, request()->user()),
            'translations' => $translations->toArray(),
        ]);
    }
}
