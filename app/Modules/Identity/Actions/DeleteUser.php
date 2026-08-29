<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Audit\Actions\RecordDataErasure;
use App\Modules\Audit\Data\DataErasureAction;
use App\Modules\Companies\Actions\EraseUserCompanyAccess;
use App\Modules\Identity\Data\DeleteUserData;
use App\Modules\Identity\Data\UserErasureState;
use App\Modules\Identity\Exceptions\UserErasureException;
use App\Modules\Identity\Models\Account;
use Illuminate\Support\Facades\DB;

final readonly class DeleteUser
{
    public function __construct(
        private EraseUserCompanyAccess $eraseCompanyAccess,
        private RecordDataErasure $recordErasure,
    ) {}

    public function handle(User $user, DeleteUserData $data): void
    {
        DB::connection(config('database.tenant_connection'))->transaction(function () use ($user, $data): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $account = Account::query()
                ->where('owner_user_id', $lockedUser->id)
                ->lockForUpdate()
                ->first();
            $state = new UserErasureState(
                accountId: $account?->id,
                ownedCompanyCount: $account?->companies()->count() ?? 0,
                membershipCount: $lockedUser->companyMemberships()->count(),
                platformOperator: $lockedUser->platformOperator()->exists(),
            );

            if (! hash_equals($state->version(), $data->stateVersion)) {
                throw UserErasureException::stateChanged();
            }

            if ($state->ownedCompanyCount > 0) {
                throw UserErasureException::ownedCompanies();
            }

            if ($state->platformOperator) {
                throw UserErasureException::platformOperator();
            }

            $this->eraseCompanyAccess->handle($lockedUser);
            DB::table('sessions')->where('user_id', $lockedUser->id)->delete();
            DB::table('password_reset_tokens')->where('email', $lockedUser->email)->delete();
            $this->recordErasure->handle(
                DataErasureAction::UserAccountErased,
                $lockedUser->id,
                $lockedUser->id,
            );
            $account?->delete();
            $lockedUser->delete();
        }, 3);
    }
}
