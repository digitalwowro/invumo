<?php

namespace App\Modules\Companies\Notifications;

use App\Models\User;
use App\Modules\Companies\Data\IssuedCompanyInvitation;
use Illuminate\Support\Facades\Notification;

final readonly class CompanyInvitationNotifier
{
    public function send(IssuedCompanyInvitation $issued, User $actor): void
    {
        $invitation = $issued->invitation;

        $notification = (new CompanyInvitationNotification(
            invitationId: $invitation->id,
            plainTextToken: $issued->plainTextToken,
            companyName: $invitation->company->name,
            inviterName: $actor->name,
            expiresAt: $invitation->expires_at,
        ))->locale($actor->language_code);

        Notification::route('mail', $invitation->invited_email)
            ->notify($notification);
    }
}
