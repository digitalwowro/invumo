<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Modules\Identity\Actions\DeleteUser;
use App\Modules\Identity\Exceptions\UserErasureException;
use App\Modules\Identity\Queries\UserErasurePage;
use App\Support\Inertia\SettingsUiTranslationBag;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(
        Request $request,
        SettingsUiTranslationBag $translations,
        UserErasurePage $erasure,
    ): Response {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'translations' => $translations->for('profile'),
            'erasure' => $erasure->for($request->user()),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('settings_ui.flash.profileUpdated'),
        ]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request, DeleteUser $deleteUser): RedirectResponse
    {
        $user = $request->user();

        try {
            $deleteUser->handle($user, $request->deletion());
        } catch (UserErasureException $exception) {
            throw ValidationException::withMessages([
                'account' => __("settings_ui.pages.profile.erasureErrors.{$exception->reason()}"),
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
