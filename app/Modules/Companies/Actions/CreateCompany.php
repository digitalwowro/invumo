<?php

namespace App\Modules\Companies\Actions;

use App\Models\User;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\Account;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class CreateCompany
{
    public function handle(Account $account, User $actor, string $name): Company
    {
        if ($account->owner_user_id !== $actor->id) {
            throw new LogicException('Only the Account owner can create its Company.');
        }

        return DB::transaction(function () use ($account, $actor, $name): Company {
            $company = Company::query()->create([
                'owning_account_id' => $account->id,
                'name' => $name,
            ]);

            $company->memberships()->create([
                'user_id' => $actor->id,
                'role' => CompanyRole::Owner,
            ]);

            return $company;
        });
    }
}
