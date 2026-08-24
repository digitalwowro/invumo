<?php

namespace App\Modules\Platform\Http\Requests;

use App\Modules\Identity\Data\PlanStatus;
use App\Modules\Platform\Data\PlanLifecycleData;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

final class UpdateAccountPlanRequest extends PlatformMutationRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'plan_id' => ['required', 'uuid', 'exists:plans,id'],
            'plan_status' => ['required', Rule::enum(PlanStatus::class)],
            'plan_started_at' => ['required', 'date'],
            'trial_ends_at' => ['nullable', 'date'],
            'access_ends_at' => ['nullable', 'date'],
            'cancel_at_period_end' => ['sometimes', 'boolean'],
            'ended_at' => ['nullable', 'date'],
        ];
    }

    public function lifecycle(): PlanLifecycleData
    {
        return new PlanLifecycleData(
            planId: $this->string('plan_id')->toString(),
            status: PlanStatus::from($this->string('plan_status')->toString()),
            startedAt: CarbonImmutable::parse($this->string('plan_started_at')->toString(), 'UTC'),
            trialEndsAt: $this->dateValue('trial_ends_at'),
            accessEndsAt: $this->dateValue('access_ends_at'),
            cancelAtPeriodEnd: $this->boolean('cancel_at_period_end'),
            endedAt: $this->dateValue('ended_at'),
        );
    }

    private function dateValue(string $key): ?CarbonImmutable
    {
        $value = $this->input($key);

        return is_string($value) && $value !== ''
            ? CarbonImmutable::parse($value, 'UTC')
            : null;
    }
}
