<?php

namespace App\Modules\Companies\Notifications;

use App\Models\User;
use App\Modules\Companies\Data\IssuedCompanyInvitation;
use App\Modules\Companies\Jobs\SendCompanyInvitation;

final readonly class CompanyInvitationNotifier
{
    public function queue(IssuedCompanyInvitation $issued, User $actor): void
    {
        $invitation = $issued->invitation;

        SendCompanyInvitation::dispatch(
            companyId: $invitation->company_id,
            invitationId: $invitation->id,
            plainTextToken: $issued->plainTextToken,
            locale: $actor->language_code,
        )->onConnection('database')->onQueue('default');
    }
}
