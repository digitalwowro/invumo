<?php

namespace Tests\Feature\Modules\Delivery;

use App\Modules\Delivery\Data\PublicDocumentToken;
use App\Modules\Delivery\Http\Middleware\CapturePublicDocumentToken;
use App\Modules\Delivery\Http\Middleware\RedactPublicDocumentToken;
use App\Modules\Delivery\Support\PublicDocumentRequestToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use RuntimeException;
use Tests\TestCase;

final class PublicDocumentTokenRedactionHttpTest extends TestCase
{
    public function test_real_kernel_routes_with_plaintext_then_redacts_every_downstream_request_surface(): void
    {
        $token = PublicDocumentToken::fromBytes(str_repeat('r', 32))->plainText;
        $route = app('router')->getRoutes()->getByName('public-quotes.show');

        $this->assertInstanceOf(Route::class, $route);
        $route->middleware(PublicDocumentRedactionProbe::class);
        $route->computedMiddleware = null;

        $this->get("/q/{$token}?download=1")
            ->assertOk()
            ->assertJson([
                'plainText' => $token,
                'routeToken' => '[redacted]',
                'rawRequestUri' => '/q/[redacted]?download=1',
                'semanticUrlRetained' => true,
            ])
            ->assertJsonPath('rawContainsPlainText', false);
    }

    public function test_capture_accepts_an_absolute_form_request_target(): void
    {
        $token = PublicDocumentToken::fromBytes(str_repeat('a', 32))->plainText;
        $request = Request::create("/q/{$token}?download=1");
        $request->server->set(
            'REQUEST_URI',
            "http://localhost/q/{$token}?download=1",
        );

        PublicDocumentRequestToken::capture($request);

        $this->assertSame($token, PublicDocumentRequestToken::plainText($request));
    }

    public function test_exception_escape_fully_redacts_the_cached_uri_and_route_parameter(): void
    {
        $token = PublicDocumentToken::fromBytes(str_repeat('e', 32))->plainText;
        $request = Request::create("/q/{$token}?download=1");
        $route = new Route(['GET'], 'q/{token}', fn () => null);
        $route->bind($request);
        $request->setRouteResolver(static fn (): Route => $route);
        $request->getRequestUri();

        try {
            app(CapturePublicDocumentToken::class)->handle(
                $request,
                fn (Request $matched): never => app(RedactPublicDocumentToken::class)->handle(
                    $matched,
                    static fn (): never => throw new RuntimeException('Expected test failure.'),
                ),
            );
            $this->fail('The downstream exception did not escape.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Expected test failure.', $exception->getMessage());
        }

        $this->assertSame('[redacted]', $request->route('token'));
        $this->assertSame('/q/[redacted]?download=1', $request->getRequestUri());
        $this->assertStringNotContainsString($token, $request->fullUrl());
    }
}

final readonly class PublicDocumentRedactionProbe
{
    public function handle(Request $request, Closure $next): JsonResponse
    {
        $plainText = PublicDocumentRequestToken::plainText($request);

        return response()->json([
            'plainText' => $plainText,
            'routeToken' => $request->route('token'),
            'rawRequestUri' => $request->server->get('REQUEST_URI'),
            'semanticUrlRetained' => str_contains($request->fullUrl(), $plainText),
            'rawContainsPlainText' => str_contains(
                (string) $request->server->get('REQUEST_URI'),
                $plainText,
            ),
        ]);
    }
}
