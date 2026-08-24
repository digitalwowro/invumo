<?php

namespace App\Modules\Companies\Jobs;

use App\Foundation\Jobs\JobIdentity;
use App\Foundation\Jobs\TenantJob;
use App\Foundation\Jobs\TenantJobExecution;
use App\Foundation\Tenancy\TenantContext;
use App\Modules\Companies\Data\CompanyInvitationDelivery;
use App\Modules\Companies\Models\CompanyInvitation;
use App\Modules\Companies\Notifications\CompanyInvitationNotification;
use App\Modules\Companies\Support\CompanyInvitationToken;
use Illuminate\Support\Facades\Notification;

final class SendCompanyInvitation extends TenantJob
{
    public function __construct(
        string $companyId,
        public readonly string $invitationId,
        public readonly string $plainTextToken,
        public readonly string $locale,
    ) {
        parent::__construct(new JobIdentity(
            companyId: $companyId,
            idempotencyKey: 'company-invitation:'.$invitationId.':'
                .CompanyInvitationToken::hash($plainTextToken),
            component: 'company.invitation_delivery',
        ));
    }

    public function handle(TenantContext $tenantContext, TenantJobExecution $execution): void
    {
        $delivery = $tenantContext->runAsSystem(
            $this->identity->companyId,
            fn (): ?CompanyInvitationDelivery => $this->resolveDelivery(),
        );

        if ($delivery === null) {
            $execution->skip('invitation_unavailable');

            return;
        }

        $notification = (new CompanyInvitationNotification(
            plainTextToken: $this->plainTextToken,
            companyName: $delivery->companyName,
            inviterName: $delivery->inviterName,
            expiresAt: $delivery->expiresAt,
        ))->locale($this->locale);

        Notification::route('mail', $delivery->email)->notifyNow($notification);
    }

    private function resolveDelivery(): ?CompanyInvitationDelivery
    {
        $invitation = CompanyInvitation::query()
            ->with(['company:id,name', 'inviter:id,name'])
            ->whereKey($this->invitationId)
            ->first();

        if ($invitation === null
            || $invitation->revoked_at !== null
            || $invitation->accepted_at !== null
            || ! $invitation->expires_at->isFuture()
            || ! hash_equals(
                $invitation->token_hash,
                CompanyInvitationToken::hash($this->plainTextToken),
            )) {
            return null;
        }

        return new CompanyInvitationDelivery(
            email: $invitation->invited_email,
            companyName: $invitation->company->name,
            inviterName: $invitation->invited_by_user_id === null
                ? $invitation->company->name
                : $invitation->inviter->name,
            expiresAt: $invitation->expires_at,
        );
    }
}
