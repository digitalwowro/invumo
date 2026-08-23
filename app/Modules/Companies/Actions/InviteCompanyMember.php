<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Data\IssuedCompanyInvitation;
use App\Modules\Companies\Exceptions\CompanyInvitationException;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyInvitation;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Notifications\CompanyInvitationNotifier;
use App\Modules\Companies\Policies\CompanyActionAuthorizer;
use App\Modules\Companies\Support\CompanyInvitationToken;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class InviteCompanyMember
{
    public function __construct(
        private CompanyActionAuthorizer $authorizer,
        private TenantContext $tenantContext,
        private RecordAuditEvent $recordAuditEvent,
        private CompanyInvitationNotifier $notifier,
    ) {}

    public function handle(Company $company, User $actor, string $email, CompanyRole $role): IssuedCompanyInvitation
    {
        if ($role === CompanyRole::Owner) {
            throw new InvalidArgumentException('An ordinary invitation cannot assign the Owner role.');
        }

        $normalizedEmail = Str::lower(trim($email));
        $token = CompanyInvitationToken::issue();

        try {
            $invitation = DB::connection(config('database.tenant_connection'))
                ->transaction(function () use ($company, $actor, $email, $normalizedEmail, $role, $token) {
                    $this->authorizer->authorize($actor, $company, CompanyAbility::ManageMembers);
                    $this->ensureNotMember($company, $normalizedEmail);
                    $this->replaceExpiredInvitation($company, $normalizedEmail);

                    $invitation = CompanyInvitation::query()->create([
                        'company_id' => $company->id,
                        'invited_email' => trim($email),
                        'invited_email_normalized' => $normalizedEmail,
                        'role' => $role,
                        'token_hash' => $token->hashed(),
                        'expires_at' => now()->addDays((int) config('invumo.company_invitation_lifetime_days')),
                        'invited_by_user_id' => $actor->id,
                    ]);

                    $this->recordCreatedAudit($company, $actor, $invitation);

                    return $invitation->load('company');
                });
        } catch (UniqueConstraintViolationException) {
            throw CompanyInvitationException::alreadyPending();
        }

        $issued = new IssuedCompanyInvitation($invitation, $token->plainText());
        $this->notifier->send($issued, $actor);

        return $issued;
    }

    private function ensureNotMember(Company $company, string $normalizedEmail): void
    {
        if (CompanyMembership::query()
            ->join('users', 'users.id', '=', 'company_memberships.user_id')
            ->where('company_memberships.company_id', $company->id)
            ->where('users.email_normalized', $normalizedEmail)
            ->exists()) {
            throw CompanyInvitationException::alreadyMember();
        }
    }

    private function replaceExpiredInvitation(Company $company, string $normalizedEmail): void
    {
        $pending = CompanyInvitation::query()
            ->where('company_id', $company->id)
            ->where('invited_email_normalized', $normalizedEmail)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->lockForUpdate()
            ->first();

        if ($pending === null) {
            return;
        }

        if ($pending->expires_at->isFuture()) {
            throw CompanyInvitationException::alreadyPending();
        }

        $pending->update(['revoked_at' => now()]);
    }

    private function recordCreatedAudit(Company $company, User $actor, CompanyInvitation $invitation): void
    {
        $this->tenantContext->runAsSystem($company->id, function () use ($actor, $invitation): void {
            $this->recordAuditEvent->handle(new AuditEventData(
                actorType: AuditActorType::User,
                actorUserId: $actor->id,
                action: 'company.invitation.created',
                targetType: 'CompanyInvitation',
                targetId: $invitation->id,
                after: AuditPayload::fromAllowedFields([
                    'role' => $invitation->role->value,
                    'expires_at' => $invitation->expires_at->toIso8601String(),
                ], ['role', 'expires_at']),
            ));
        });
    }
}
