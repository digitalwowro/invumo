<?php

namespace App\Modules\Platform\Queries;

use App\Modules\Identity\Data\PlanStatus;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Platform\Data\PlatformCursorPage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final readonly class PlatformAccountsPage
{
    /** @return array<string, mixed> */
    public function for(Request $request, bool $lifecycleOnly = false): array
    {
        $search = trim($request->string('q')->toString());
        $status = $request->enum('status', PlanStatus::class);
        $expiryDays = $request->integer('expiry_days');
        $query = Account::query()
            ->with(['owner:id,name,email', 'plan:id,name'])
            ->withCount('companies')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $normalized = '%'.mb_strtolower($search).'%';
                $query->whereHas('owner', fn (Builder $owner) => $owner
                    ->whereRaw('lower(name) LIKE ?', [$normalized])
                    ->orWhere('email_normalized', 'like', $normalized));
            })
            ->when($status !== null, fn (Builder $query) => $query
                ->where('plan_status', $status->value))
            ->when($request->boolean('cancel_at_period_end'), fn (Builder $query) => $query
                ->where('cancel_at_period_end', true));

        if (in_array($expiryDays, [7, 30], true)) {
            $now = CarbonImmutable::now();
            $until = $now->addDays($expiryDays);
            $query->where(function (Builder $query) use ($now, $until): void {
                $query->whereBetween('trial_ends_at', [$now, $until])
                    ->orWhereBetween('access_ends_at', [$now, $until]);
            });
        }

        $page = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(25)
            ->withQueryString();

        return [
            'search' => $search,
            'selectedStatus' => $status?->value,
            'selectedExpiryDays' => in_array($expiryDays, [7, 30], true) ? $expiryDays : null,
            'cancelAtPeriodEndOnly' => $request->boolean('cancel_at_period_end'),
            'lifecycleOnly' => $lifecycleOnly,
            'plans' => $this->plans(),
            'page' => PlatformCursorPage::from($page, $this->accountItem(...))->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    private function accountItem(Account $account): array
    {
        return [
            'id' => $account->id,
            'ownerName' => $account->owner->name,
            'ownerEmail' => $account->owner->email,
            'planId' => $account->plan_id,
            'planName' => $account->plan->name,
            'planStatus' => $account->plan_status->value,
            'planStartedAt' => $account->plan_started_at->toIso8601String(),
            'trialEndsAt' => $account->trial_ends_at?->toIso8601String(),
            'accessEndsAt' => $account->access_ends_at?->toIso8601String(),
            'cancelAtPeriodEnd' => $account->cancel_at_period_end,
            'endedAt' => $account->ended_at?->toIso8601String(),
            'suspended' => $account->suspended_at !== null,
            'companyCount' => $account->companies_count,
            'suspendUrl' => route('platform.accounts.suspension.store', $account, false),
            'reactivateUrl' => route('platform.accounts.suspension.destroy', $account, false),
            'planUrl' => route('platform.accounts.plan.update', $account, false),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function plans(): array
    {
        $plans = [];

        foreach (Plan::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']) as $plan) {
            $plans[] = ['id' => $plan->id, 'name' => $plan->name];
        }

        return $plans;
    }
}
