<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Exceptions\CompanyMembershipException;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Policies\CompanyMembershipActionAuthorizer;
use Illuminate\Support\Facades\DB;

final readonly class ChangeCompanyMemberRole
{
    public function __construct(
        private CompanyMembershipActionAuthorizer $authorizer,
        private TenantContext $tenantContext,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        CompanyMembership $target,
        CompanyRole $role,
    ): CompanyMembership {
        if ($role === CompanyRole::Owner) {
            throw CompanyMembershipException::invalidRole();
        }

        return DB::connection(config('database.tenant_connection'))
            ->transaction(function () use ($company, $actor, $target, $role): CompanyMembership {
                $locked = $this->authorizer->target($actor, $company, $target)['target'];
                $previousRole = $locked->role;

                if ($previousRole === $role) {
                    throw CompanyMembershipException::roleUnchanged();
                }

                $locked->update(['role' => $role]);
                $this->recordChange($company, $actor, $locked, $previousRole);

                return $locked->refresh();
            });
    }

    private function recordChange(
        Company $company,
        User $actor,
        CompanyMembership $membership,
        CompanyRole $previousRole,
    ): void {
        $this->tenantContext->runAsSystem($company->id, function () use ($actor, $membership, $previousRole): void {
            $this->recordAuditEvent->handle(new AuditEventData(
                actorType: AuditActorType::User,
                actorUserId: $actor->id,
                action: 'company.membership.role_changed',
                targetType: 'CompanyMembership',
                targetId: $membership->id,
                before: AuditPayload::fromAllowedFields([
                    'role' => $previousRole->value,
                ], ['role']),
                after: AuditPayload::fromAllowedFields([
                    'role' => $membership->role->value,
                ], ['role']),
            ));
        });
    }
}
