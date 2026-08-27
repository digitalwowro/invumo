<?php

namespace App\Modules\Delivery\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;

final class PublicDocumentRequestToken
{
    private const ATTRIBUTE = 'invumo.public_document_token';

    public static function capture(Request $request): void
    {
        if (is_string($request->attributes->get(self::ATTRIBUTE))) {
            return;
        }

        $rawUri = (string) $request->server->get('REQUEST_URI', '');

        if (preg_match('#^/(q|i)/([^/?]+)(?:/(?:pdf|decision))?(?:\?|$)#', $rawUri, $matches) === 1) {
            $request->attributes->set(self::ATTRIBUTE, $matches[2]);
        }
    }

    public static function matchesRoute(Request $request): bool
    {
        $routeToken = $request->route('token');
        $captured = self::plainText($request);

        return is_string($routeToken)
            && $captured !== ''
            && hash_equals($routeToken, $captured);
    }

    public static function redact(Request $request): void
    {
        $rawUri = (string) $request->server->get('REQUEST_URI', '');
        $safeUri = preg_replace('#^/(q|i)/[^/?]+#', '/$1/[redacted]', $rawUri);

        if (! is_string($safeUri) || $safeUri === $rawUri) {
            return;
        }

        $route = $request->route();

        if ($route instanceof Route && is_string($request->route('token'))) {
            $route->setParameter('token', '[redacted]');
        }

        $server = $request->server->all();
        $server['REQUEST_URI'] = $safeUri;
        $server['UNENCODED_URL'] = $safeUri;

        // Reinitialization is the supported way to invalidate Symfony's cached
        // request URI/path/base values after route matching has completed.
        $request->initialize(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $server,
            $request->getContent(),
        );
    }

    public static function plainText(Request $request): string
    {
        $token = $request->attributes->get(self::ATTRIBUTE);

        return is_string($token) ? $token : '';
    }
}
