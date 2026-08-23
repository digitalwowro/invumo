<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Policies\CompanyMembershipActionAuthorizer;
use Illuminate\Support\Facades\DB;

final readonly class LeaveCompany
{
    public function __construct(
        private CompanyMembershipActionAuthorizer $authorizer,
        private TenantContext $tenantContext,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor): void
    {
        DB::connection(config('database.tenant_connection'))
            ->transaction(function () use ($company, $actor): void {
                $membership = $this->authorizer->self($actor, $company);
                $membershipId = $membership->id;
                $role = $membership->role;

                $membership->delete();

                $this->tenantContext->runAsSystem($company->id, function () use ($actor, $membershipId, $role): void {
                    $this->recordAuditEvent->handle(new AuditEventData(
                        actorType: AuditActorType::User,
                        actorUserId: $actor->id,
                        action: 'company.membership.left',
                        targetType: 'CompanyMembership',
                        targetId: $membershipId,
                        before: AuditPayload::fromAllowedFields([
                            'role' => $role->value,
                        ], ['role']),
                    ));
                });
            });
    }
}
