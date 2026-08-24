<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Platform\Actions\UpdateAccountPlan;
use App\Modules\Platform\Exceptions\PlatformOperationException;
use App\Modules\Platform\Http\Requests\UpdateAccountPlanRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final readonly class AccountPlanController
{
    public function update(
        UpdateAccountPlanRequest $request,
        string $account,
        UpdateAccountPlan $updatePlan,
    ): RedirectResponse {
        try {
            $updatePlan->handle(
                $request->user(),
                $account,
                $request->lifecycle(),
                $request->string('reason')->toString(),
            );
        } catch (PlatformOperationException) {
            throw ValidationException::withMessages([
                'operation' => __('platform_ui.errors.operation_failed'),
            ]);
        }

        return back()->with('status', __('platform_ui.feedback.plan_updated'));
    }
}
