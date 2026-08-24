<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Platform\Data\PlatformAuditEventData;
use App\Modules\Platform\Data\PlatformRole;
use App\Modules\Platform\Exceptions\PlatformOperatorException;
use App\Modules\Platform\Models\PlatformOperator;
use App\Modules\Platform\Support\PlatformOperatorMutationLock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class RevokePlatformOwner
{
    public function __construct(
        private RecordPlatformAuditEvent $recordAudit,
        private PlatformOperatorMutationLock $mutationLock,
    ) {}

    public function handle(string $userId, string $reason): void
    {
        DB::connection(config('database.tenant_connection'))
            ->transaction(function () use ($reason, $userId): void {
                $this->mutationLock->acquire();

                $operators = PlatformOperator::query()
                    ->orderBy('id')
                    ->get();
                $users = $this->lockOperatorUsers($operators);
                $user = $users->firstWhere('id', $userId);
                $operator = $operators->firstWhere('user_id', $userId);

                if (! $user instanceof User || ! $operator instanceof PlatformOperator) {
                    throw new PlatformOperatorException('The User is not a Platform Owner.');
                }

                if ($user->email_verified_at === null || $user->suspended_at !== null) {
                    throw new PlatformOperatorException(
                        'A Platform Owner must be a verified, unsuspended User.',
                    );
                }

                $activeOwners = $operators->filter(function (PlatformOperator $candidate) use ($users): bool {
                    $candidateUser = $users->firstWhere('id', $candidate->user_id);

                    return $candidateUser instanceof User
                        && $candidateUser->email_verified_at !== null
                        && $candidateUser->suspended_at === null;
                });

                if ($activeOwners->count() <= 1) {
                    throw new PlatformOperatorException('The last active Platform Owner cannot be removed.');
                }

                $operator->delete();

                $this->recordAudit->handle(new PlatformAuditEventData(
                    action: 'platform_operator.revoked',
                    targetType: 'User',
                    targetId: $user->id,
                    reason: $reason,
                    before: AuditPayload::fromAllowedFields([
                        'role' => PlatformRole::Owner->value,
                    ], ['role']),
                ));
            });
    }

    /**
     * Lock every operator User in stable order after locking operator rows.
     *
     * @param  Collection<int, PlatformOperator>  $operators
     * @return Collection<int, User>
     */
    private function lockOperatorUsers(Collection $operators): Collection
    {
        return User::query()
            ->whereIn('id', $operators->pluck('user_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
