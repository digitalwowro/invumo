<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Identity\Models\Account;
use App\Modules\Platform\Data\PlatformAuditEventData;
use App\Modules\Platform\Exceptions\PlatformOperationException;
use App\Modules\Platform\Policies\PlatformMutationAuthorizer;
use Illuminate\Support\Facades\DB;

final readonly class SetAccountSuspension
{
    public function __construct(
        private PlatformMutationAuthorizer $authorize,
        private RecordPlatformAuditEvent $recordAudit,
    ) {}

    public function handle(User $actor, string $accountId, bool $suspended, string $reason): Account
    {
        return DB::connection(config('database.tenant_connection'))
            ->transaction(function () use ($accountId, $actor, $reason, $suspended): Account {
                $lockedActor = $this->authorize->lock($actor);
                $account = Account::query()->whereKey($accountId)->lockForUpdate()->firstOrFail();
                $wasSuspended = $account->suspended_at !== null;

                if ($wasSuspended === $suspended) {
                    throw new PlatformOperationException(
                        $suspended ? 'The Account is already suspended.' : 'The Account is already active.',
                    );
                }

                $account->forceFill(['suspended_at' => $suspended ? now() : null])->save();

                $this->recordAudit->handle(new PlatformAuditEventData(
                    actorUserId: $lockedActor->id,
                    action: $suspended ? 'account.suspended' : 'account.reactivated',
                    targetType: 'Account',
                    targetId: $account->id,
                    reason: $reason,
                    before: $this->payload($wasSuspended),
                    after: $this->payload($suspended),
                ));

                return $account;
            });
    }

    private function payload(bool $suspended): AuditPayload
    {
        return AuditPayload::fromAllowedFields(['suspended' => $suspended], ['suspended']);
    }
}
