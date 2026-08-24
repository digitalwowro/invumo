<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Platform\Actions\SetUserSuspension;
use App\Modules\Platform\Exceptions\PlatformOperationException;
use App\Modules\Platform\Http\Requests\PlatformMutationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final readonly class UserSuspensionController
{
    public function store(
        PlatformMutationRequest $request,
        string $user,
        SetUserSuspension $suspension,
    ): RedirectResponse {
        $this->set($request, $user, $suspension, true);

        return back()->with('status', __('platform_ui.feedback.user_suspended'));
    }

    public function destroy(
        PlatformMutationRequest $request,
        string $user,
        SetUserSuspension $suspension,
    ): RedirectResponse {
        $this->set($request, $user, $suspension, false);

        return back()->with('status', __('platform_ui.feedback.user_reactivated'));
    }

    private function set(
        PlatformMutationRequest $request,
        string $user,
        SetUserSuspension $suspension,
        bool $suspended,
    ): void {
        try {
            $suspension->handle(
                $request->user(),
                $user,
                $suspended,
                $request->string('reason')->toString(),
            );
        } catch (PlatformOperationException) {
            throw ValidationException::withMessages([
                'operation' => __('platform_ui.errors.operation_failed'),
            ]);
        }
    }
}
