<?php

use App\Foundation\Http\SafeErrorResponse;
use App\Http\Middleware\AttachCorrelationId;
use App\Http\Middleware\EnsureAuthenticatedUserIsActive;
use App\Http\Middleware\EnterCompanyContext;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetApplicationLocale;
use App\Modules\Delivery\Http\Middleware\RedactPublicDocumentToken;
use App\Modules\Platform\Http\Middleware\EnsurePlatformOperator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RedactPublicDocumentToken::class);
        $middleware->encryptCookies(except: ['sidebar_state']);

        $middleware->alias([
            'company.context' => EnterCompanyContext::class,
            'platform.operator' => EnsurePlatformOperator::class,
        ]);

        $middleware->web(append: [
            AttachCorrelationId::class,
            SetApplicationLocale::class,
            EnsureAuthenticatedUserIsActive::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(
            fn (Response $response, Throwable $_exception, Request $request): Response => app(SafeErrorResponse::class)
                ->render($response, $request),
        );
    })->create();
