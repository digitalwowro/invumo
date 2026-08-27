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

        if (preg_match(
            '~^(?:https?://[^/?#]+)?/(q|i)/([^/?]+)(?:/(?:pdf|decision))?(?:\?|$)~i',
            $rawUri,
            $matches,
        ) === 1) {
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
        $safeUri = self::safeUri($request);

        if ($safeUri === null) {
            return;
        }

        self::redactRouteParameter($request);

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

    public static function redactMatchedRoute(Request $request): void
    {
        $safeUri = self::safeUri($request);

        if ($safeUri === null) {
            return;
        }

        self::redactRouteParameter($request);
        $request->server->set('REQUEST_URI', $safeUri);
        $request->server->set('UNENCODED_URL', $safeUri);
    }

    public static function plainText(Request $request): string
    {
        $token = $request->attributes->get(self::ATTRIBUTE);

        return is_string($token) ? $token : '';
    }

    private static function redactRouteParameter(Request $request): void
    {
        $route = $request->route();

        if ($route instanceof Route && is_string($request->route('token'))) {
            $route->setParameter('token', '[redacted]');
        }
    }

    private static function safeUri(Request $request): ?string
    {
        $rawUri = (string) $request->server->get('REQUEST_URI', '');

        if (preg_match(
            '~^(?:https?://[^/?#]+)?/(q|i)/[^/?]+~i',
            $rawUri,
        ) !== 1) {
            return null;
        }

        $safeUri = preg_replace(
            '~^((?:https?://[^/?#]+)?)/(q|i)/[^/?]+~i',
            '$1/$2/[redacted]',
            $rawUri,
        );

        return is_string($safeUri) ? $safeUri : null;
    }
}
