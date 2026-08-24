<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Identity\Data\PlanStatus;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Platform\Data\PlanLifecycleData;
use App\Modules\Platform\Data\PlatformAuditEventData;
use App\Modules\Platform\Exceptions\PlatformOperationException;
use App\Modules\Platform\Policies\PlatformMutationAuthorizer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class UpdateAccountPlan
{
    public function __construct(
        private PlatformMutationAuthorizer $authorize,
        private RecordPlatformAuditEvent $recordAudit,
    ) {}

    public function handle(
        User $actor,
        string $accountId,
        PlanLifecycleData $data,
        string $reason,
    ): Account {
        return DB::connection(config('database.tenant_connection'))
            ->transaction(function () use ($accountId, $actor, $data, $reason): Account {
                $lockedActor = $this->authorize->lock($actor);
                $account = Account::query()->whereKey($accountId)->lockForUpdate()->firstOrFail();
                $plan = Plan::query()->whereKey($data->planId)->where('active', true)->first();

                if ($plan === null) {
                    throw new PlatformOperationException('The selected Plan is unavailable.');
                }

                $this->ensureValid($data);
                $before = $this->payload($account);

                $account->forceFill([
                    'plan_id' => $plan->id,
                    'plan_status' => $data->status,
                    'plan_started_at' => $data->startedAt,
                    'trial_ends_at' => $data->trialEndsAt,
                    'access_ends_at' => $data->accessEndsAt,
                    'cancel_at_period_end' => $data->cancelAtPeriodEnd,
                    'ended_at' => $data->endedAt,
                ])->save();

                $this->recordAudit->handle(new PlatformAuditEventData(
                    actorUserId: $lockedActor->id,
                    action: 'account.plan_updated',
                    targetType: 'Account',
                    targetId: $account->id,
                    reason: $reason,
                    before: $before,
                    after: $this->payload($account),
                ));

                return $account;
            });
    }

    private function ensureValid(PlanLifecycleData $data): void
    {
        if ($data->trialEndsAt?->lessThan($data->startedAt)) {
            throw new PlatformOperationException('Trial end cannot precede plan start.');
        }

        if ($data->accessEndsAt?->lessThan($data->startedAt)) {
            throw new PlatformOperationException('Access end cannot precede plan start.');
        }

        if ($data->cancelAtPeriodEnd && $data->accessEndsAt === null) {
            throw new PlatformOperationException('Cancel at period end requires an access end.');
        }

        if ($data->status === PlanStatus::Trialing && ! $data->trialEndsAt?->isFuture()) {
            throw new PlatformOperationException('Trialing requires a future trial end.');
        }

        $ended = in_array($data->status, [PlanStatus::Canceled, PlanStatus::Expired], true);

        if ($ended !== ($data->endedAt !== null)) {
            throw new PlatformOperationException('The end timestamp must match the ended lifecycle state.');
        }
    }

    private function payload(Account $account): AuditPayload
    {
        return AuditPayload::fromAllowedFields([
            'plan_id' => $account->plan_id,
            'plan_status' => $account->plan_status->value,
            'plan_started_at' => $this->timestamp($account->plan_started_at),
            'trial_ends_at' => $this->timestamp($account->trial_ends_at),
            'access_ends_at' => $this->timestamp($account->access_ends_at),
            'cancel_at_period_end' => $account->cancel_at_period_end,
            'ended_at' => $this->timestamp($account->ended_at),
        ], [
            'plan_id',
            'plan_status',
            'plan_started_at',
            'trial_ends_at',
            'access_ends_at',
            'cancel_at_period_end',
            'ended_at',
        ]);
    }

    private function timestamp(?CarbonImmutable $value): ?string
    {
        return $value?->toIso8601String();
    }
}
