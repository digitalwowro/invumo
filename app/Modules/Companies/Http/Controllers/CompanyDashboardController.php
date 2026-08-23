<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Support\Inertia\DashboardTranslationBag;
use Inertia\Inertia;
use Inertia\Response;

class CompanyDashboardController extends Controller
{
    public function __invoke(
        Company $company,
        DashboardTranslationBag $translations,
    ): Response {
        return Inertia::render('dashboard', [
            'company' => ['name' => $company->name],
            'membersUrl' => route('company-members.index', $company, false),
            'translations' => $translations->toArray(),
        ]);
    }
}
