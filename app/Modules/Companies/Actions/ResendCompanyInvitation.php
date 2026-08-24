<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\IssuedCompanyInvitation;
use App\Modules\Companies\Exceptions\CompanyInvitationException;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyInvitation;
use App\Modules\Companies\Notifications\CompanyInvitationNotifier;
use App\Modules\Companies\Policies\CompanyActionAuthorizer;
use App\Modules\Companies\Support\CompanyInvitationToken;
use Illuminate\Support\Facades\DB;

final readonly class ResendCompanyInvitation
{
    public function __construct(
        private CompanyActionAuthorizer $authorizer,
        private TenantContext $tenantContext,
        private RecordAuditEvent $recordAuditEvent,
        private CompanyInvitationNotifier $notifier,
    ) {}

    public function handle(Company $company, User $actor, CompanyInvitation $invitation): IssuedCompanyInvitation
    {
        $token = CompanyInvitationToken::issue();

        return DB::connection(config('database.tenant_connection'))
            ->transaction(function () use ($company, $actor, $invitation, $token) {
                $this->authorizer->authorize($actor, $company, CompanyAbility::ManageMembers);
                $locked = $this->lockPending($company, $invitation);
                $beforeExpiry = $locked->expires_at->toIso8601String();

                $locked->update([
                    'token_hash' => $token->hashed(),
                    'expires_at' => now()->addDays((int) config('invumo.company_invitation_lifetime_days')),
                ]);

                $this->tenantContext->runAsSystem($company->id, function () use ($actor, $beforeExpiry, $locked): void {
                    $this->recordAuditEvent->handle(new AuditEventData(
                        actorType: AuditActorType::User,
                        actorUserId: $actor->id,
                        action: 'company.invitation.resent',
                        targetType: 'CompanyInvitation',
                        targetId: $locked->id,
                        before: AuditPayload::fromAllowedFields([
                            'expires_at' => $beforeExpiry,
                        ], ['expires_at']),
                        after: AuditPayload::fromAllowedFields([
                            'expires_at' => $locked->expires_at->toIso8601String(),
                        ], ['expires_at']),
                    ));
                });

                $issued = new IssuedCompanyInvitation($locked, $token->plainText());
                $this->notifier->queue($issued, $actor);

                return $issued;
            });
    }

    private function lockPending(Company $company, CompanyInvitation $invitation): CompanyInvitation
    {
        $locked = CompanyInvitation::query()
            ->whereKey($invitation->id)
            ->where('company_id', $company->id)
            ->lockForUpdate()
            ->first();

        if ($locked === null || $locked->revoked_at !== null || $locked->accepted_at !== null) {
            throw CompanyInvitationException::unavailable();
        }

        return $locked;
    }
}
