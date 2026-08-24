<?php

namespace App\Http\Middleware;

use App\Foundation\Auth\ImpersonationSession;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureAuthenticatedUserIsActive
{
    public function __construct(private ImpersonationSession $impersonation) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->suspended_at === null) {
            return $next($request);
        }

        if ($this->impersonation->active($request)) {
            if ($request->routeIs([
                'platform.impersonation.suspended',
                'platform.impersonation.destroy',
            ])) {
                return $next($request);
            }

            return new RedirectResponse(route('platform.impersonation.suspended'));
        }

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return new RedirectResponse(route('login'));
    }
}
