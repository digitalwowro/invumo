<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Exceptions\CompanyInvitationException;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyInvitation;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Support\CompanyInvitationToken;
use Illuminate\Support\Facades\DB;

final readonly class AcceptCompanyInvitation
{
    public function __construct(
        private TenantContext $tenantContext,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(User $actor, string $plainTextToken): Company
    {
        return DB::connection(config('database.tenant_connection'))
            ->transaction(function () use ($actor, $plainTextToken): Company {
                $tokenHash = CompanyInvitationToken::hash($plainTextToken);
                $candidate = CompanyInvitation::query()
                    ->where('token_hash', $tokenHash)
                    ->first();

                if ($candidate === null) {
                    throw CompanyInvitationException::unavailable();
                }

                $company = Company::query()
                    ->whereKey($candidate->company_id)
                    ->whereNull('archived_at')
                    ->lockForUpdate()
                    ->first();

                if ($company === null) {
                    throw CompanyInvitationException::unavailable();
                }

                $lockedActor = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $invitation = CompanyInvitation::query()
                    ->whereKey($candidate->id)
                    ->where('token_hash', $tokenHash)
                    ->lockForUpdate()
                    ->first();

                $this->ensureAvailable($invitation);

                if ($lockedActor->email_normalized !== $invitation->invited_email_normalized) {
                    throw CompanyInvitationException::emailMismatch();
                }

                $membership = CompanyMembership::query()
                    ->where('company_id', $company->id)
                    ->where('user_id', $lockedActor->id)
                    ->lockForUpdate()
                    ->first();

                if ($membership === null) {
                    $membership = CompanyMembership::query()->create([
                        'company_id' => $company->id,
                        'user_id' => $lockedActor->id,
                        'role' => $invitation->role,
                    ]);
                }

                $invitation->update([
                    'accepted_at' => now(),
                    'accepted_by_user_id' => $lockedActor->id,
                ]);

                $this->recordAcceptedAudit($company, $lockedActor, $invitation, $membership);

                return $company;
            });
    }

    private function ensureAvailable(?CompanyInvitation $invitation): void
    {
        if (
            $invitation === null
            || $invitation->revoked_at !== null
            || $invitation->accepted_at !== null
            || $invitation->expires_at->lessThanOrEqualTo(now())
        ) {
            throw CompanyInvitationException::unavailable();
        }
    }

    private function recordAcceptedAudit(
        Company $company,
        User $actor,
        CompanyInvitation $invitation,
        CompanyMembership $membership,
    ): void {
        $this->tenantContext->runAsSystem($company->id, function () use ($actor, $invitation, $membership): void {
            $this->recordAuditEvent->handle(new AuditEventData(
                actorType: AuditActorType::User,
                actorUserId: $actor->id,
                action: 'company.invitation.accepted',
                targetType: 'CompanyMembership',
                targetId: $membership->id,
                after: AuditPayload::fromAllowedFields([
                    'invitation_id' => $invitation->id,
                    'role' => $membership->role->value,
                ], ['invitation_id', 'role']),
            ));
        });
    }
}
