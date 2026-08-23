<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Exceptions\CompanyInvitationException;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyInvitation;
use App\Modules\Companies\Policies\CompanyActionAuthorizer;
use Illuminate\Support\Facades\DB;

final readonly class RevokeCompanyInvitation
{
    public function __construct(
        private CompanyActionAuthorizer $authorizer,
        private TenantContext $tenantContext,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Company $company, User $actor, CompanyInvitation $invitation): void
    {
        DB::connection(config('database.tenant_connection'))
            ->transaction(function () use ($company, $actor, $invitation): void {
                $this->authorizer->authorize($actor, $company, CompanyAbility::ManageMembers);

                $locked = CompanyInvitation::query()
                    ->whereKey($invitation->id)
                    ->where('company_id', $company->id)
                    ->lockForUpdate()
                    ->first();

                if ($locked === null || $locked->revoked_at !== null || $locked->accepted_at !== null) {
                    throw CompanyInvitationException::unavailable();
                }

                $locked->update(['revoked_at' => now()]);

                $this->tenantContext->runAsSystem($company->id, function () use ($actor, $locked): void {
                    $this->recordAuditEvent->handle(new AuditEventData(
                        actorType: AuditActorType::User,
                        actorUserId: $actor->id,
                        action: 'company.invitation.revoked',
                        targetType: 'CompanyInvitation',
                        targetId: $locked->id,
                        after: AuditPayload::fromAllowedFields([
                            'revoked' => true,
                        ], ['revoked']),
                    ));
                });
            });
    }
}
