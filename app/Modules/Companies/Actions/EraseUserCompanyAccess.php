<?php

namespace App\Modules\Companies\Actions;

use App\Models\User;
use App\Modules\Companies\Models\CompanyInvitation;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Support\CompanyInvitationIdentityLock;

final readonly class EraseUserCompanyAccess
{
    public function __construct(private CompanyInvitationIdentityLock $identityLock) {}

    public function handle(User $user): void
    {
        $this->identityLock->acquire($user->email_normalized);

        $memberships = CompanyMembership::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $invitations = CompanyInvitation::query()
            ->where('invited_email_normalized', $user->email_normalized)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $memberships->each->delete();

        foreach ($invitations as $invitation) {
            if ($invitation->accepted_at === null && $invitation->revoked_at === null) {
                $invitation->delete();

                continue;
            }

            $invitation->update([
                'invited_email' => null,
                'invited_email_normalized' => null,
                'identity_erased_at' => now(),
            ]);
        }
    }
}
