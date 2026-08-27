<?php

namespace Tests\Unit\Modules\Delivery;

use App\Modules\Delivery\Data\PublicDocumentToken;
use App\Modules\Delivery\Http\Middleware\RedactPublicDocumentToken;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class PublicDocumentTokenRedactionTest extends TestCase
{
    public function test_route_and_request_uri_are_redacted_before_downstream_handling(): void
    {
        $token = PublicDocumentToken::fromBytes(str_repeat('r', 32))->plainText;
        $request = Request::create("/q/{$token}/pdf?download=1");
        $route = new class($token)
        {
            public function __construct(private string $token) {}

            public function parameter(string $name, mixed $default = null): mixed
            {
                return $name === 'token' ? $this->token : $default;
            }

            public function setParameter(string $name, mixed $value): void
            {
                if ($name === 'token') {
                    $this->token = (string) $value;
                }
            }
        };
        $request->setRouteResolver(fn (): object => $route);

        $response = (new RedactPublicDocumentToken)->handle(
            $request,
            function (Request $redacted) use ($token): Response {
                $this->assertSame($token, RedactPublicDocumentToken::plainText($redacted));
                $this->assertSame('[redacted]', $redacted->route('token'));
                $this->assertSame('/q/[redacted]/pdf?download=1', $redacted->getRequestUri());
                $this->assertStringNotContainsString($token, $redacted->fullUrl());

                return new Response('ok');
            },
        );

        $this->assertSame('ok', $response->getContent());
    }
}
