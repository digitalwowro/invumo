<?php

namespace App\Modules\Platform\Policies;

use App\Models\User;
use App\Modules\Platform\Queries\CurrentPlatformOperator;
use App\Modules\Platform\Support\PlatformOperatorMutationLock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class PlatformMutationAuthorizer
{
    public function __construct(
        private CurrentPlatformOperator $currentOperator,
        private PlatformOperatorMutationLock $mutationLock,
    ) {}

    public function lock(User $actor): User
    {
        $connection = DB::connection(config('database.tenant_connection'));

        if ($connection->transactionLevel() === 0) {
            throw new LogicException('Platform mutation authorization requires a transaction.');
        }

        $this->mutationLock->acquire();
        $lockedActor = User::query()->whereKey($actor->id)->lockForUpdate()->first();

        if ($lockedActor === null || $this->currentOperator->for($lockedActor) === null) {
            throw new AuthorizationException;
        }

        return $lockedActor;
    }
}
