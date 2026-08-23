<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Modules\Identity\Actions\RevokeUserSessions;
use App\Support\Inertia\SettingsUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(SettingsUiTranslationBag $translations): Response
    {
        $props = [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'translations' => $translations->for('security'),
        ];

        return Inertia::render('settings/security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(
        PasswordUpdateRequest $request,
        RevokeUserSessions $revokeUserSessions,
    ): RedirectResponse {
        $request->user()->forceFill([
            'password' => $request->password,
            'remember_token' => Str::random(60),
        ])->save();

        $revokeUserSessions->handle($request->user(), $request->session()->getId());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('settings_ui.flash.passwordUpdated'),
        ]);

        return back();
    }
}
