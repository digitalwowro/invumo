<?php

namespace App\Modules\Platform\Http\Middleware;

use App\Models\User;
use App\Modules\Platform\Queries\CurrentPlatformOperator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsurePlatformOperator
{
    public function __construct(private CurrentPlatformOperator $currentOperator) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $this->currentOperator->for($user) !== null,
            403,
        );

        return $next($request);
    }
}
