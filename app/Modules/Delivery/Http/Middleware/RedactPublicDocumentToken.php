<?php

namespace App\Modules\Delivery\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RedactPublicDocumentToken
{
    private const ATTRIBUTE = 'invumo.public_document_token';

    public function handle(Request $request, Closure $next): Response
    {
        $rawUri = (string) $request->server->get('REQUEST_URI', '');
        $matched = preg_match('#^/(q|i)/([^/?]+)(?:/pdf)?(?:\?|$)#', $rawUri, $matches) === 1;

        if ($matched) {
            $request->attributes->set(self::ATTRIBUTE, $matches[2]);
            $safeUri = (string) preg_replace(
                '#^/(q|i)/[^/?]+#',
                '/$1/[redacted]',
                $rawUri,
            );
            $request->server->set('REQUEST_URI', $safeUri);
            $request->server->set('UNENCODED_URL', $safeUri);
        }

        if (is_string($request->route('token'))) {
            $request->route()?->setParameter('token', '[redacted]');
        }

        return $next($request);
    }

    public static function plainText(Request $request): string
    {
        $token = $request->attributes->get(self::ATTRIBUTE);

        return is_string($token) ? $token : '';
    }
}
