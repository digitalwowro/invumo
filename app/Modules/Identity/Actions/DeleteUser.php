<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class DeleteUser
{
    public function handle(User $user): void
    {
        DB::connection(config('database.tenant_connection'))->transaction(function () use ($user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $account = $lockedUser->account()->lockForUpdate()->first();

            if ($account?->companies()->exists()) {
                throw new LogicException('Transfer or delete owned Companies before deleting this User.');
            }

            if ($lockedUser->companyMemberships()->exists()) {
                throw new LogicException('Leave all Companies before deleting this User.');
            }

            $account?->delete();
            $lockedUser->delete();
        });
    }
}
