<?php

namespace App\Modules\Companies\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyInvitation;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Policies\CompanyAuthorization;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CompanyMembersPage
{
    public function __construct(private CompanyAuthorization $authorization) {}

    /** @return array{canManageMembers: bool, members: array<int, array<string, mixed>>, invitations: array<int, array<string, mixed>>} */
    public function for(Company $company, User $actor): array
    {
        $actorMembership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $actor->id)
            ->first();

        if (
            $actorMembership === null
            || ! $this->authorization->allows($actorMembership->role, CompanyAbility::ViewCompany)
        ) {
            throw new AuthorizationException;
        }

        $canManageMembers = $this->authorization->allows(
            $actorMembership->role,
            CompanyAbility::ManageMembers,
        );

        $members = CompanyMembership::query()
            ->with('user:id,name,email')
            ->where('company_id', $company->id)
            ->orderByRaw("CASE role WHEN 'OWNER' THEN 1 WHEN 'ADMIN' THEN 2 ELSE 3 END")
            ->orderBy('created_at')
            ->get()
            ->map(fn (CompanyMembership $membership): array => [
                'id' => $membership->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role->value,
                'isCurrentUser' => $membership->user_id === $actor->id,
            ])
            ->values()
            ->all();

        $invitations = $canManageMembers
            ? CompanyInvitation::query()
                ->where('company_id', $company->id)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (CompanyInvitation $invitation): array => [
                    'id' => $invitation->id,
                    'email' => $invitation->invited_email,
                    'role' => $invitation->role->value,
                    'expiresAt' => $invitation->expires_at->toIso8601String(),
                    'expired' => $invitation->expires_at->lessThanOrEqualTo(now()),
                    'resendUrl' => route('company-invitations.resend', [$company, $invitation], false),
                    'revokeUrl' => route('company-invitations.revoke', [$company, $invitation], false),
                ])
                ->values()
                ->all()
            : [];

        return [
            'canManageMembers' => $canManageMembers,
            'members' => $members,
            'invitations' => $invitations,
        ];
    }
}
