<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Actions\ChangeCompanyMemberRole;
use App\Modules\Companies\Actions\InviteCompanyMember;
use App\Modules\Companies\Actions\LeaveCompany;
use App\Modules\Companies\Actions\RemoveCompanyMember;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Exceptions\CompanyInvitationException;
use App\Modules\Companies\Exceptions\CompanyMembershipException;
use App\Modules\Companies\Http\Requests\StoreCompanyInvitationRequest;
use App\Modules\Companies\Http\Requests\UpdateCompanyMemberRoleRequest;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Queries\CompanyMembersPage;
use App\Modules\Companies\Queries\CompanySettingsNavigation;
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
        CompanySettingsNavigation $navigation,
        CompaniesUiTranslationBag $translations,
    ): Response {
        return Inertia::render('companies/settings/members', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            ...$page->for($company, $request->user()),
            'companySettingsNavigation' => $navigation->for($company, $request->user())['items'],
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

    public function update(
        UpdateCompanyMemberRoleRequest $request,
        Company $company,
        CompanyMembership $membership,
        ChangeCompanyMemberRole $changeRole,
    ): RedirectResponse {
        try {
            $changeRole->handle(
                $company,
                $request->user(),
                $membership,
                CompanyRole::from($request->string('role')->toString()),
            );
        } catch (CompanyMembershipException $exception) {
            $this->membershipValidationError($exception);
        }

        return back()->with('status', __('companies_ui.members.feedback.role_changed'));
    }

    public function destroy(
        Request $request,
        Company $company,
        CompanyMembership $membership,
        RemoveCompanyMember $removeMember,
    ): RedirectResponse {
        try {
            $removeMember->handle($company, $request->user(), $membership);
        } catch (CompanyMembershipException $exception) {
            $this->membershipValidationError($exception);
        }

        return back()->with('status', __('companies_ui.members.feedback.removed'));
    }

    public function leave(
        Request $request,
        Company $company,
        LeaveCompany $leaveCompany,
    ): RedirectResponse {
        try {
            $leaveCompany->handle($company, $request->user());
        } catch (CompanyMembershipException $exception) {
            $this->membershipValidationError($exception);
        }

        $request->session()->forget('last_company_id');
        $request->session()->put('company_context.skip_remember_once', true);

        return redirect()->route('companies.index')
            ->with('status', __('companies_ui.members.feedback.left'));
    }

    private function membershipValidationError(CompanyMembershipException $exception): never
    {
        throw ValidationException::withMessages([
            'membership' => __("companies_ui.members.errors.{$exception->reason()}"),
        ]);
    }
}
