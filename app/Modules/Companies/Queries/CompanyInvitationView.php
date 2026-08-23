<?php

namespace App\Modules\Companies\Queries;

use App\Models\User;
use App\Modules\Companies\Models\CompanyInvitation;
use App\Modules\Companies\Support\CompanyInvitationToken;

final readonly class CompanyInvitationView
{
    /** @return array<string, mixed> */
    public function for(string $plainTextToken, ?User $actor): array
    {
        $invitation = CompanyInvitation::query()
            ->with('company:id,name')
            ->where('token_hash', CompanyInvitationToken::hash($plainTextToken))
            ->first();

        if ($invitation === null) {
            return $this->unavailable('invalid');
        }

        $status = match (true) {
            $invitation->accepted_at !== null => 'accepted',
            $invitation->revoked_at !== null => 'revoked',
            $invitation->expires_at->lessThanOrEqualTo(now()) => 'expired',
            default => 'pending',
        };
        $emailMatches = $actor !== null
            && $actor->email_normalized === $invitation->invited_email_normalized;
        $mayViewPrivateDetails = $status === 'pending' && $emailMatches;

        return [
            'available' => $status === 'pending',
            'status' => $status,
            'companyName' => $invitation->company->name,
            'invitedEmail' => $mayViewPrivateDetails ? $invitation->invited_email : null,
            'role' => $mayViewPrivateDetails ? $invitation->role->value : null,
            'expiresAt' => $mayViewPrivateDetails
                ? $invitation->expires_at->toIso8601String()
                : null,
            'authenticated' => $actor !== null,
            'emailMatches' => $emailMatches,
            'emailVerified' => $actor !== null
                && $emailMatches
                && $actor->hasVerifiedEmail(),
        ];
    }

    /** @return array<string, mixed> */
    private function unavailable(string $status): array
    {
        return [
            'available' => false,
            'status' => $status,
            'companyName' => null,
            'invitedEmail' => null,
            'role' => null,
            'expiresAt' => null,
            'authenticated' => false,
            'emailMatches' => false,
            'emailVerified' => false,
        ];
    }
}
