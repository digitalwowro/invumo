<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Platform\Data\PlatformAuditEventData;
use App\Modules\Platform\Data\PlatformRole;
use App\Modules\Platform\Exceptions\PlatformOperatorException;
use App\Modules\Platform\Models\PlatformOperator;
use App\Modules\Platform\Support\PlatformOperatorMutationLock;
use Illuminate\Support\Facades\DB;

final readonly class GrantPlatformOwner
{
    public function __construct(
        private RecordPlatformAuditEvent $recordAudit,
        private PlatformOperatorMutationLock $mutationLock,
    ) {}

    public function handle(string $userId, string $reason): PlatformOperator
    {
        return DB::connection(config('database.tenant_connection'))
            ->transaction(function () use ($reason, $userId): PlatformOperator {
                $this->mutationLock->acquire();

                $user = User::query()->lockForUpdate()->findOrFail($userId);

                if ($user->email_verified_at === null || $user->suspended_at !== null) {
                    throw new PlatformOperatorException(
                        'A Platform Owner must be a verified, unsuspended User.',
                    );
                }

                $existing = PlatformOperator::query()
                    ->where('user_id', $user->id)
                    ->first();

                if ($existing !== null) {
                    throw new PlatformOperatorException('The User is already a Platform Owner.');
                }

                $operator = PlatformOperator::query()->create([
                    'user_id' => $user->id,
                    'role' => PlatformRole::Owner,
                ]);

                $this->recordAudit->handle(new PlatformAuditEventData(
                    action: 'platform_operator.granted',
                    targetType: 'User',
                    targetId: $user->id,
                    reason: $reason,
                    after: AuditPayload::fromAllowedFields([
                        'role' => PlatformRole::Owner->value,
                    ], ['role']),
                ));

                return $operator;
            });
    }
}
