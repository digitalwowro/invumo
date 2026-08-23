<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Actions\AcceptCompanyInvitation;
use App\Modules\Companies\Actions\ResendCompanyInvitation;
use App\Modules\Companies\Actions\RevokeCompanyInvitation;
use App\Modules\Companies\Exceptions\CompanyInvitationException;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyInvitation;
use App\Modules\Companies\Queries\CompanyInvitationView;
use App\Support\Inertia\CompaniesUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CompanyInvitationController extends Controller
{
    public function show(
        Request $request,
        string $token,
        CompanyInvitationView $view,
        CompaniesUiTranslationBag $translations,
    ): Response {
        $invitation = $view->for($token, $request->user());

        if (
            $invitation['available'] === true
            && (
                $request->user() === null
                || ($invitation['emailMatches'] === true && $invitation['emailVerified'] === false)
            )
        ) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return Inertia::render('auth/company-invitation', [
            'invitation' => $invitation,
            'acceptUrl' => route('company-invitations.accept', $token, false),
            'loginUrl' => route('login', absolute: false),
            'registerUrl' => route('register', absolute: false),
            'verificationUrl' => route('verification.notice', absolute: false),
            'translations' => $translations->toArray()['invitation'],
        ]);
    }

    public function accept(
        Request $request,
        string $token,
        AcceptCompanyInvitation $accept,
    ): RedirectResponse {
        try {
            $company = $accept->handle($request->user(), $token);
        } catch (CompanyInvitationException $exception) {
            throw ValidationException::withMessages([
                'invitation' => __("companies_ui.invitation.errors.{$exception->reason()}"),
            ]);
        }

        return redirect()->route('companies.dashboard', $company)
            ->with('status', __('companies_ui.invitation.feedback.accepted'));
    }

    public function resend(
        Request $request,
        Company $company,
        CompanyInvitation $invitation,
        ResendCompanyInvitation $resend,
    ): RedirectResponse {
        $this->runManagedAction(
            fn () => $resend->handle($company, $request->user(), $invitation),
        );

        return back()->with('status', __('companies_ui.members.feedback.resent'));
    }

    public function revoke(
        Request $request,
        Company $company,
        CompanyInvitation $invitation,
        RevokeCompanyInvitation $revoke,
    ): RedirectResponse {
        $this->runManagedAction(
            fn () => $revoke->handle($company, $request->user(), $invitation),
        );

        return back()->with('status', __('companies_ui.members.feedback.revoked'));
    }

    /** @param callable(): mixed $action */
    private function runManagedAction(callable $action): void
    {
        try {
            $action();
        } catch (CompanyInvitationException $exception) {
            throw ValidationException::withMessages([
                'invitation' => __("companies_ui.members.errors.{$exception->reason()}"),
            ]);
        }
    }
}
