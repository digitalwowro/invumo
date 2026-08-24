<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Platform\Data\PlatformAuditEventData;
use App\Modules\Platform\Queries\CurrentPlatformOperator;
use App\Modules\Platform\Support\PlatformOperatorMutationLock;
use Illuminate\Support\Facades\DB;

final readonly class EndUserImpersonation
{
    public function __construct(
        private PlatformOperatorMutationLock $mutationLock,
        private CurrentPlatformOperator $currentOperator,
        private RecordPlatformAuditEvent $recordAudit,
    ) {}

    public function handle(string $originalUserId, User $effectiveUser): ?User
    {
        return DB::connection(config('database.tenant_connection'))
            ->transaction(function () use ($effectiveUser, $originalUserId): ?User {
                $this->mutationLock->acquire();
                $users = User::query()
                    ->whereIn('id', array_values(array_unique([
                        $originalUserId,
                        $effectiveUser->id,
                    ])))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $original = $users->get($originalUserId);
                $effective = $users->get($effectiveUser->id);
                $restorable = $original instanceof User
                    && $this->currentOperator->for($original) !== null;

                $this->recordAudit->handle(new PlatformAuditEventData(
                    actorUserId: $effective instanceof User ? $effective->id : null,
                    action: 'user_impersonation.ended',
                    targetType: 'User',
                    targetId: $effectiveUser->id,
                    after: AuditPayload::fromAllowedFields([
                        'original_user_restored' => $restorable,
                    ], ['original_user_restored']),
                ));

                return $restorable ? $original : null;
            });
    }
}
