<?php

namespace App\Modules\Delivery\Http\Middleware;

use App\Modules\Delivery\Support\PublicDocumentRequestToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RedactPublicDocumentToken
{
    public function handle(Request $request, Closure $next): Response
    {
        PublicDocumentRequestToken::capture($request);
        abort_unless(PublicDocumentRequestToken::matchesRoute($request), 404);
        PublicDocumentRequestToken::redactMatchedRoute($request);

        return $next($request);
    }
}
