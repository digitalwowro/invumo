<?php

namespace App\Modules\Platform\Queries;

use App\Foundation\Auth\ImpersonationSession;
use App\Models\User;
use App\Modules\Platform\Data\PlatformAbility;
use Illuminate\Http\Request;

final readonly class PlatformContextProps
{
    public function __construct(
        private CurrentPlatformOperator $currentOperator,
        private ImpersonationSession $impersonation,
    ) {}

    /** @return array<string, mixed> */
    public function for(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User || $this->currentOperator->for($user) === null) {
            return [];
        }

        return [
            'platformContext' => [
                'label' => __('platform_ui.label'),
                'navigationDescription' => __('platform_ui.navigation_description'),
                'overviewUrl' => route('platform.overview'),
                'navigation' => [
                    'overview' => __('platform_ui.navigation.overview'),
                    'users' => __('platform_ui.navigation.users'),
                    'accounts' => __('platform_ui.navigation.accounts'),
                    'companies' => __('platform_ui.navigation.companies'),
                    'planLifecycle' => __('platform_ui.navigation.plan_lifecycle'),
                    'audit' => __('platform_ui.navigation.audit'),
                ],
                'routes' => [
                    'users' => route('platform.users.index'),
                    'accounts' => route('platform.accounts.index'),
                    'companies' => route('platform.companies.index'),
                    'planLifecycle' => route('platform.plan-lifecycle.index'),
                    'audit' => route('platform.audit.index'),
                ],
                'abilities' => array_replace(array_fill_keys(array_map(
                    static fn (PlatformAbility $ability): string => $ability->value,
                    PlatformAbility::cases(),
                ), true), [
                    PlatformAbility::ImpersonateUsers->value => ! $this->impersonation->active($request),
                ]),
            ],
        ];
    }
}
