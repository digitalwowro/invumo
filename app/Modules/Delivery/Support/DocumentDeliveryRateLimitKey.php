<?php

namespace App\Modules\Delivery\Support;

use App\Modules\Companies\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class DocumentDeliveryRateLimitKey
{
    public static function account(Request $request): string
    {
        $company = $request->route('company');
        $companyId = $company instanceof Company ? $company->id : $company;
        $userId = (string) $request->user()?->getAuthIdentifier();
        $accountId = null;

        if ($userId !== '' && is_string($companyId) && Str::isUuid($companyId)) {
            $accountId = Company::query()
                ->whereKey($companyId)
                ->whereHas(
                    'memberships',
                    fn (Builder $memberships): Builder => $memberships->where('user_id', $userId),
                )
                ->value('owning_account_id');
        }

        return hash('sha256', is_string($accountId) ? $accountId : 'unresolved:'.$userId);
    }

    public static function user(Request $request): string
    {
        return hash('sha256', (string) $request->user()?->getAuthIdentifier());
    }
}
