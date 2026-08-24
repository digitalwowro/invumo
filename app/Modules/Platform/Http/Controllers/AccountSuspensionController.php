<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Platform\Actions\SetAccountSuspension;
use App\Modules\Platform\Exceptions\PlatformOperationException;
use App\Modules\Platform\Http\Requests\PlatformMutationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final readonly class AccountSuspensionController
{
    public function store(
        PlatformMutationRequest $request,
        string $account,
        SetAccountSuspension $suspension,
    ): RedirectResponse {
        $this->set($request, $account, $suspension, true);

        return back()->with('status', __('platform_ui.feedback.account_suspended'));
    }

    public function destroy(
        PlatformMutationRequest $request,
        string $account,
        SetAccountSuspension $suspension,
    ): RedirectResponse {
        $this->set($request, $account, $suspension, false);

        return back()->with('status', __('platform_ui.feedback.account_reactivated'));
    }

    private function set(
        PlatformMutationRequest $request,
        string $account,
        SetAccountSuspension $suspension,
        bool $suspended,
    ): void {
        try {
            $suspension->handle(
                $request->user(),
                $account,
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
