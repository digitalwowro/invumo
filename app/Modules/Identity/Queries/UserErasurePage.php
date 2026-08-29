<?php

namespace App\Modules\Identity\Queries;

use App\Models\User;
use App\Modules\Identity\Data\UserErasureState;
use RuntimeException;

final readonly class UserErasurePage
{
    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        $account = $user->account()->first(['id']);
        $state = new UserErasureState(
            accountId: $account?->id,
            ownedCompanyCount: $account?->companies()->count() ?? 0,
            membershipCount: $user->companyMemberships()->count(),
            platformOperator: $user->platformOperator()->exists(),
        );

        return [
            'stateVersion' => $state->version(),
            'membershipCount' => $state->membershipCount,
            'guard' => [
                'blocked' => $state->blocked(),
                'description' => $this->description($state),
            ],
        ];
    }

    private function description(UserErasureState $state): ?string
    {
        $key = match (true) {
            $state->ownedCompanyCount > 0 => 'owned_companies',
            $state->platformOperator => 'platform_operator',
            default => null,
        };

        if ($key === null) {
            return null;
        }

        $translation = __("settings_ui.pages.profile.erasureGuards.{$key}", [
            'companies' => $state->ownedCompanyCount,
        ]);

        if (! is_string($translation)) {
            throw new RuntimeException('The User erasure dependency text must be a string.');
        }

        return $translation;
    }
}
