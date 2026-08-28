<?php

namespace Tests\Unit\Integrations;

use App\Integrations\ZeptoMail\ZeptoMailWebhook;
use App\Modules\Delivery\Contracts\ProviderWebhookRequestException;
use App\Modules\Delivery\Data\ProviderWebhookEventType;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ZeptoMailWebhookTest extends TestCase
{
    private const SECRET = 'test-webhook-secret-at-least-32-bytes';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.zeptomail.webhook_secret', self::SECRET);
    }

    public function test_it_authenticates_and_maps_only_privacy_safe_fields(): void
    {
        $receivedAt = CarbonImmutable::parse('2026-08-28T12:00:00Z');
        $data = $this->payload('email_open');

        $event = app(ZeptoMailWebhook::class)->parse(
            'data='.urlencode($data),
            self::SECRET,
            $receivedAt,
        );

        $this->assertNotNull($event);
        $this->assertSame(ProviderWebhookEventType::Opened, $event->type);
        $this->assertSame('event-123', $event->providerEventIdentifier);
        $this->assertSame('0198f45d-9e53-7b65-a631-7d98f6065f63', $event->clientReference);
        $this->assertSame(1787918390, $event->occurredAt->getTimestamp());
        $this->assertObjectNotHasProperty('ipAddress', $event);
        $this->assertObjectNotHasProperty('browser', $event);
    }

    #[DataProvider('invalidRequests')]
    public function test_it_rejects_invalid_encoding(string $body): void
    {
        $this->expectException(ProviderWebhookRequestException::class);

        app(ZeptoMailWebhook::class)->parse(
            $body,
            self::SECRET,
            CarbonImmutable::parse('2026-08-28T12:00:00Z'),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function invalidRequests(): iterable
    {
        yield 'malformed percent encoding' => ['data=%ZZ'];
        yield 'extra form field' => ['data=%7B%7D&extra=true'];
        yield 'invalid JSON' => ['data=%7B'];
    }

    #[DataProvider('invalidAuthenticationKeys')]
    public function test_it_requires_the_exact_static_authentication_key(?string $key): void
    {
        $this->expectException(ProviderWebhookRequestException::class);

        app(ZeptoMailWebhook::class)->parse(
            'data='.urlencode($this->payload('delivered')),
            $key,
            CarbonImmutable::parse('2026-08-28T12:00:00Z'),
        );
    }

    /** @return iterable<string, array{string|null}> */
    public static function invalidAuthenticationKeys(): iterable
    {
        yield 'missing' => [null];
        yield 'different' => ['different-webhook-secret-at-least-32-bytes'];
        yield 'trailing whitespace' => [self::SECRET.' '];
    }

    public function test_authenticated_unknown_events_are_ignored(): void
    {
        $receivedAt = CarbonImmutable::parse('2026-08-28T12:00:00Z');
        $data = $this->payload('provider_future_event');

        $this->assertNull(app(ZeptoMailWebhook::class)->parse(
            'data='.urlencode($data),
            self::SECRET,
            $receivedAt,
        ));
    }

    public function test_an_authenticated_empty_verification_probe_is_accepted(): void
    {
        $this->assertNull(app(ZeptoMailWebhook::class)->parse(
            '',
            self::SECRET,
            CarbonImmutable::parse('2026-08-28T12:00:00Z'),
        ));
    }

    public function test_an_authenticated_agent_event_without_an_invumo_reference_is_ignored(): void
    {
        $data = json_encode([
            'event_name' => ['delivered'],
            'webhook_request_id' => 'provider-test-event',
            'event_message' => [['email_info' => ['client_reference' => 'provider-test']]],
        ], JSON_THROW_ON_ERROR);

        $this->assertNull(app(ZeptoMailWebhook::class)->parse(
            'data='.urlencode($data),
            self::SECRET,
            CarbonImmutable::parse('2026-08-28T12:00:00Z'),
        ));
    }

    private function payload(string $event): string
    {
        return json_encode([
            'event_name' => [$event],
            'webhook_request_id' => 'event-123',
            'event_message' => [[
                'email_info' => [
                    'client_reference' => '0198f45d-9e53-7b65-a631-7d98f6065f63',
                ],
                'event_data' => [[
                    'details' => [[
                        'time' => 1787918390,
                        'ip_address' => '192.0.2.10',
                        'browser' => 'Private Browser',
                    ]],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);
    }
}
