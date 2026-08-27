<?php

namespace Tests\Feature\Modules\Delivery;

use App\Modules\Delivery\Data\PublicDocumentToken;
use App\Modules\Delivery\Support\PublicDocumentRequestToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
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
                'requestUri' => '/q/[redacted]?download=1',
                'path' => 'q/[redacted]',
            ])
            ->assertJsonPath('containsPlainText', false);
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
            'requestUri' => $request->getRequestUri(),
            'path' => $request->path(),
            'containsPlainText' => str_contains($request->fullUrl(), $plainText),
        ]);
    }
}
