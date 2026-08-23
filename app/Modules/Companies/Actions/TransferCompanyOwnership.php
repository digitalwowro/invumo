<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Policies\CompanyOwnershipTransferAuthorizer;
use Illuminate\Support\Facades\DB;

final readonly class TransferCompanyOwnership
{
    public function __construct(
        private CompanyOwnershipTransferAuthorizer $authorizer,
        private TenantContext $tenantContext,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        CompanyMembership $destination,
        bool $retainFormerOwner = true,
    ): Company {
        return DB::connection(config('database.tenant_connection'))
            ->transaction(function () use ($company, $actor, $destination, $retainFormerOwner): Company {
                $participants = $this->authorizer->authorize($actor, $company, $destination);
                $activeCompany = $participants['company'];
                $owner = $participants['owner'];
                $target = $participants['destination'];
                $previousAccountId = $activeCompany->owning_account_id;

                if ($retainFormerOwner) {
                    $owner->update(['role' => CompanyRole::Admin]);
                } else {
                    $owner->delete();
                }

                $target->update(['role' => CompanyRole::Owner]);
                $activeCompany->update([
                    'owning_account_id' => $participants['destinationAccount']->id,
                ]);

                $this->recordTransfer(
                    $activeCompany,
                    $actor,
                    $target,
                    $previousAccountId,
                    $retainFormerOwner,
                );

                return $activeCompany->refresh();
            });
    }

    private function recordTransfer(
        Company $company,
        User $actor,
        CompanyMembership $newOwner,
        string $previousAccountId,
        bool $retainedFormerOwner,
    ): void {
        $this->tenantContext->runAsSystem($company->id, function () use (
            $actor,
            $company,
            $newOwner,
            $previousAccountId,
            $retainedFormerOwner,
        ): void {
            $this->recordAuditEvent->handle(new AuditEventData(
                actorType: AuditActorType::User,
                actorUserId: $actor->id,
                action: 'company.ownership.transferred',
                targetType: 'Company',
                targetId: $company->id,
                before: AuditPayload::fromAllowedFields([
                    'owning_account_id' => $previousAccountId,
                    'owner_user_id' => $actor->id,
                ], ['owning_account_id', 'owner_user_id']),
                after: AuditPayload::fromAllowedFields([
                    'owning_account_id' => $company->owning_account_id,
                    'owner_user_id' => $newOwner->user_id,
                    'former_owner_outcome' => $retainedFormerOwner ? 'ADMIN' : 'REMOVED',
                ], ['owning_account_id', 'owner_user_id', 'former_owner_outcome']),
            ));
        });
    }
}
