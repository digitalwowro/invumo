<?php

namespace App\Modules\Platform\Queries;

use App\Models\User;
use App\Modules\Platform\Data\PlatformCursorPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final readonly class PlatformUsersPage
{
    /** @return array<string, mixed> */
    public function for(Request $request): array
    {
        $search = trim($request->string('q')->toString());
        $query = User::query()
            ->with(['account.plan:id,name'])
            ->withCount('companyMemberships')
            ->withExists('platformOperator')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereRaw('lower(name) LIKE ?', ['%'.mb_strtolower($search).'%'])
                        ->orWhere('email_normalized', 'like', '%'.mb_strtolower($search).'%');
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(25)
            ->withQueryString();

        return [
            'search' => $search,
            'page' => PlatformCursorPage::from($query, fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'verified' => $user->email_verified_at !== null,
                'suspended' => $user->suspended_at !== null,
                'lastLoginAt' => $user->last_login_at?->toIso8601String(),
                'createdAt' => $user->created_at?->toIso8601String(),
                'planName' => $user->account?->plan?->name,
                'planStatus' => $user->account?->plan_status?->value,
                'companyCount' => $user->company_memberships_count,
                'isOperator' => (bool) $user->platform_operator_exists,
                'suspendUrl' => route('platform.users.suspension.store', $user, false),
                'reactivateUrl' => route('platform.users.suspension.destroy', $user, false),
                'impersonateUrl' => route('platform.users.impersonation.store', $user, false),
            ])->toArray(),
        ];
    }
}
