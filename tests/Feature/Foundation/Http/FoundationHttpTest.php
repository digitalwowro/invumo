<?php

namespace Tests\Feature\Foundation\Http;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FoundationHttpTest extends TestCase
{
    public function test_web_requests_receive_a_server_generated_correlation_id(): void
    {
        $response = $this->withHeader('X-Request-ID', 'attacker-controlled')
            ->get('/');
        $correlationId = $response->headers->get('X-Request-ID');

        $this->assertIsString($correlationId);
        $this->assertTrue(Str::isUuid($correlationId));
        $this->assertNotSame('attacker-controlled', $correlationId);
    }

    public function test_inertia_errors_are_localized_and_hide_internal_messages(): void
    {
        $this->withoutVite();
        Route::middleware('web')->get(
            '/foundation/error-probe',
            fn () => abort(404, 'private-customer-value'),
        );
        $response = $this->get('/foundation/error-probe')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('errors/show')
                ->where('status', 404)
                ->where('translations.page.title', 'We could not find that page'));

        $response->assertDontSee('private-customer-value', false);
    }

    public function test_json_errors_use_the_same_non_disclosing_boundary(): void
    {
        Route::middleware('web')->get(
            '/foundation/json-error-probe',
            fn () => abort(503, 'private-provider-message'),
        );

        $response = $this->getJson('/foundation/json-error-probe')
            ->assertServiceUnavailable()
            ->assertExactJson([
                'status' => 503,
                'message' => 'Invumo is temporarily unavailable',
            ]);

        $response->assertDontSee('private-provider-message', false);
    }
}
