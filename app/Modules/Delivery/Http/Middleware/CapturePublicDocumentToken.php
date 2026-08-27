<?php

namespace App\Modules\Delivery\Http\Middleware;

use App\Modules\Delivery\Support\PublicDocumentRequestToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class CapturePublicDocumentToken
{
    public function handle(Request $request, Closure $next): Response
    {
        PublicDocumentRequestToken::capture($request);

        try {
            return $next($request);
        } catch (Throwable $exception) {
            PublicDocumentRequestToken::redact($request);

            throw $exception;
        }
    }
}
