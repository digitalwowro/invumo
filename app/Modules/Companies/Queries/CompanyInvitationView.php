<?php

namespace App\Modules\Companies\Queries;

use App\Models\User;
use App\Modules\Companies\Models\CompanyInvitation;
use App\Modules\Companies\Support\CompanyInvitationToken;
use Illuminate\Support\Str;

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

        return [
            'available' => $status === 'pending',
            'status' => $status,
            'companyName' => $invitation->company->name,
            'invitedEmail' => $invitation->invited_email,
            'role' => $invitation->role->value,
            'expiresAt' => $invitation->expires_at->toIso8601String(),
            'authenticated' => $actor !== null,
            'emailMatches' => $actor !== null
                && Str::lower($actor->email) === $invitation->invited_email_normalized,
            'emailVerified' => $actor?->hasVerifiedEmail() ?? false,
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
