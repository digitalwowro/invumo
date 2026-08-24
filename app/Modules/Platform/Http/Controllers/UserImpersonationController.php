<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Foundation\Auth\ImpersonationSession;
use App\Models\User;
use App\Modules\Platform\Actions\EndUserImpersonation;
use App\Modules\Platform\Actions\StartUserImpersonation;
use App\Modules\Platform\Http\Requests\StartUserImpersonationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserImpersonationController
{
    public function suspended(): Response
    {
        return Inertia::render('impersonation/suspended', [
            'translations' => [
                'title' => __('common.impersonation.suspended_title'),
                'description' => __('common.impersonation.suspended_description'),
            ],
        ]);
    }

    public function store(
        StartUserImpersonationRequest $request,
        string $user,
        ImpersonationSession $impersonation,
        StartUserImpersonation $start,
    ): RedirectResponse {
        abort_if($impersonation->active($request), 409, __('common.impersonation.nested_denied'));

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $target = $start->handle($actor, $user);
        $lastCompanyId = $request->session()->get('last_company_id');
        $request->session()->forget(['last_company_id', 'auth.password_confirmed_at']);
        Auth::login($target);
        $impersonation->begin(
            $request,
            $actor->id,
            is_string($lastCompanyId) ? $lastCompanyId : null,
        );
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function destroy(
        Request $request,
        ImpersonationSession $impersonation,
        EndUserImpersonation $end,
    ): RedirectResponse {
        $effectiveUser = $request->user();
        $originalUserId = $impersonation->originalUserId($request);
        abort_unless($effectiveUser instanceof User && $originalUserId !== null, 409);

        $originalCompanyId = $impersonation->originalCompanyId($request);
        $originalUser = $end->handle($originalUserId, $effectiveUser);
        $impersonation->forget($request);
        $request->session()->forget(['last_company_id', 'auth.password_confirmed_at']);

        if ($originalUser === null) {
            Auth::guard()->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        Auth::login($originalUser);

        if ($originalCompanyId !== null) {
            $request->session()->put('last_company_id', $originalCompanyId);
        }

        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('platform.users.index')
            ->with('status', __('common.impersonation.ended'));
    }
}
