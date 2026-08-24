<?php

namespace App\Modules\Platform\Queries;

use App\Foundation\Auth\ImpersonationSession;
use App\Models\User;
use Illuminate\Http\Request;

final readonly class ImpersonationContextProps
{
    public function __construct(private ImpersonationSession $impersonation) {}

    /** @return array<string, mixed> */
    public function for(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User || ! $this->impersonation->active($request)) {
            return [];
        }

        return [
            'impersonation' => [
                'active' => true,
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'message' => __('common.impersonation.message', [
                    'name' => $user->name,
                    'email' => $user->email,
                ]),
                'exitLabel' => __('common.impersonation.exit'),
                'exitUrl' => route('platform.impersonation.destroy'),
            ],
        ];
    }
}
