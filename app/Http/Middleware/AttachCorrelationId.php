<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AttachCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = (string) Str::uuid7();

        $request->attributes->set('correlation_id', $correlationId);
        Log::shareContext(['correlation_id' => $correlationId]);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $correlationId);

        return $response;
    }
}
