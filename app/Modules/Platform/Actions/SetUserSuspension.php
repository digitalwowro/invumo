<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Identity\Actions\RevokeUserSessions;
use App\Modules\Platform\Data\PlatformAuditEventData;
use App\Modules\Platform\Exceptions\PlatformOperationException;
use App\Modules\Platform\Models\PlatformOperator;
use App\Modules\Platform\Policies\PlatformMutationAuthorizer;
use Illuminate\Support\Facades\DB;

final readonly class SetUserSuspension
{
    public function __construct(
        private PlatformMutationAuthorizer $authorize,
        private RevokeUserSessions $revokeSessions,
        private RecordPlatformAuditEvent $recordAudit,
    ) {}

    public function handle(User $actor, string $targetUserId, bool $suspended, string $reason): User
    {
        return DB::connection(config('database.tenant_connection'))
            ->transaction(function () use ($actor, $reason, $suspended, $targetUserId): User {
                $lockedActor = $this->authorize->lock($actor);
                $target = User::query()->whereKey($targetUserId)->lockForUpdate()->firstOrFail();

                if ($lockedActor->is($target)) {
                    throw new PlatformOperationException('A Platform Owner cannot suspend itself.');
                }

                if (PlatformOperator::query()->where('user_id', $target->id)->exists()) {
                    throw new PlatformOperationException('A Platform Owner cannot be suspended.');
                }

                $wasSuspended = $target->suspended_at !== null;

                if ($wasSuspended === $suspended) {
                    throw new PlatformOperationException(
                        $suspended ? 'The User is already suspended.' : 'The User is already active.',
                    );
                }

                $target->forceFill(['suspended_at' => $suspended ? now() : null])->save();

                if ($suspended) {
                    $this->revokeSessions->handle($target);
                }

                $this->recordAudit->handle(new PlatformAuditEventData(
                    actorUserId: $lockedActor->id,
                    action: $suspended ? 'user.suspended' : 'user.reactivated',
                    targetType: 'User',
                    targetId: $target->id,
                    reason: $reason,
                    before: $this->payload($wasSuspended),
                    after: $this->payload($suspended),
                ));

                return $target;
            });
    }

    private function payload(bool $suspended): AuditPayload
    {
        return AuditPayload::fromAllowedFields(['suspended' => $suspended], ['suspended']);
    }
}
