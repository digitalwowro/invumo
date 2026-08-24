<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Platform\Data\PlatformAuditEventData;
use App\Modules\Platform\Policies\PlatformMutationAuthorizer;
use Illuminate\Support\Facades\DB;

final readonly class StartUserImpersonation
{
    public function __construct(
        private PlatformMutationAuthorizer $authorize,
        private RecordPlatformAuditEvent $recordAudit,
    ) {}

    public function handle(User $actor, string $targetUserId): User
    {
        return DB::connection(config('database.tenant_connection'))
            ->transaction(function () use ($actor, $targetUserId): User {
                $lockedActor = $this->authorize->lock($actor);
                $target = User::query()->whereKey($targetUserId)->lockForUpdate()->firstOrFail();

                $this->recordAudit->handle(new PlatformAuditEventData(
                    actorUserId: $lockedActor->id,
                    action: 'user_impersonation.started',
                    targetType: 'User',
                    targetId: $target->id,
                    after: AuditPayload::fromAllowedFields([
                        'effective_user_id' => $target->id,
                    ], ['effective_user_id']),
                ));

                return $target;
            });
    }
}
