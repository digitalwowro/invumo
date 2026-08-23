<?php

namespace App\Modules\Companies\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyInvitation;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Policies\CompanyAuthorization;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CompanyMembersPage
{
    public function __construct(private CompanyAuthorization $authorization) {}

    /** @return array{canManageMembers: bool, canLeaveCompany: bool, leaveUrl: ?string, canTransferOwnership: bool, transferOwnershipUrl: ?string, transferCandidates: array<int, array<string, string>>, members: array<int, array<string, mixed>>, invitations: array<int, array<string, mixed>>} */
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
        $canTransferOwnership = $this->authorization->allows(
            $actorMembership->role,
            CompanyAbility::TransferOwnership,
        );

        $members = CompanyMembership::query()
            ->with('user:id,name,email')
            ->where('company_id', $company->id)
            ->orderByRaw("CASE role WHEN 'OWNER' THEN 1 WHEN 'ADMIN' THEN 2 ELSE 3 END")
            ->orderBy('created_at')
            ->get()
            ->map(function (CompanyMembership $membership) use ($actor, $company, $canManageMembers): array {
                $isCurrentUser = $membership->user_id === $actor->id;
                $canManageTarget = $canManageMembers
                    && ! $isCurrentUser
                    && $membership->role !== CompanyRole::Owner;

                return [
                    'id' => $membership->id,
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'role' => $membership->role->value,
                    'isCurrentUser' => $isCurrentUser,
                    'nextRole' => $canManageTarget
                        ? ($membership->role === CompanyRole::Admin
                            ? CompanyRole::Member->value
                            : CompanyRole::Admin->value)
                        : null,
                    'updateUrl' => $canManageTarget
                        ? route('company-members.update', [$company, $membership], false)
                        : null,
                    'removeUrl' => $canManageTarget
                        ? route('company-members.destroy', [$company, $membership], false)
                        : null,
                ];
            })
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

        $transferCandidates = $canTransferOwnership
            ? CompanyMembership::query()
                ->with('user:id,name,email')
                ->where('company_id', $company->id)
                ->where('user_id', '<>', $actor->id)
                ->whereIn('role', [CompanyRole::Admin, CompanyRole::Member])
                ->orderBy('created_at')
                ->get()
                ->map(fn (CompanyMembership $membership): array => [
                    'id' => $membership->id,
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'role' => $membership->role->value,
                ])
                ->values()
                ->all()
            : [];

        return [
            'canManageMembers' => $canManageMembers,
            'canLeaveCompany' => $actorMembership->role !== CompanyRole::Owner,
            'leaveUrl' => $actorMembership->role !== CompanyRole::Owner
                ? route('company-members.leave', $company, false)
                : null,
            'canTransferOwnership' => $canTransferOwnership,
            'transferOwnershipUrl' => $canTransferOwnership
                ? route('company-ownership.update', $company, false)
                : null,
            'transferCandidates' => $transferCandidates,
            'members' => $members,
            'invitations' => $invitations,
        ];
    }
}
