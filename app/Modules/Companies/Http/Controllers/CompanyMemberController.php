<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Actions\InviteCompanyMember;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Exceptions\CompanyInvitationException;
use App\Modules\Companies\Http\Requests\StoreCompanyInvitationRequest;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyMembersPage;
use App\Support\Inertia\CompaniesUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CompanyMemberController extends Controller
{
    public function index(
        Request $request,
        Company $company,
        CompanyMembersPage $page,
        CompaniesUiTranslationBag $translations,
    ): Response {
        return Inertia::render('companies/members/index', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            ...$page->for($company, $request->user()),
            'storeUrl' => route('company-invitations.store', $company, false),
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
        ]);
    }

    public function store(
        StoreCompanyInvitationRequest $request,
        Company $company,
        InviteCompanyMember $invite,
    ): RedirectResponse {
        try {
            $invite->handle(
                $company,
                $request->user(),
                $request->string('email')->toString(),
                CompanyRole::from($request->string('role')->toString()),
            );
        } catch (CompanyInvitationException $exception) {
            throw ValidationException::withMessages([
                'email' => __("companies_ui.members.errors.{$exception->reason()}"),
            ]);
        }

        return back()->with('status', __('companies_ui.members.feedback.invited'));
    }
}
