<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Queries\AccessibleCompanies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanyLandingController extends Controller
{
    public function __invoke(Request $request, AccessibleCompanies $companies): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        $memberships = $companies->for($user);

        if ($memberships->isEmpty()) {
            return redirect()->route('companies.create');
        }

        $lastCompanyId = $request->session()->get('last_company_id');
        $membership = $memberships->firstWhere('company_id', $lastCompanyId)
            ?? $memberships->firstOrFail();

        return redirect()->route('companies.dashboard', $membership->company_id);
    }
}
