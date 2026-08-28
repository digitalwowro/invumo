<?php

namespace Tests\Feature\Modules\Delivery;

use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Delivery\Contracts\SendsProviderEmail;
use App\Modules\Delivery\Data\ProviderDelivery;
use App\Modules\Delivery\Data\ProviderDeliveryResult;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryAttempt;
use App\Modules\Delivery\Models\EmailProviderEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\DocumentDeliveryTestCase;

final class ZeptoMailWebhookHttpTest extends DocumentDeliveryTestCase
{
    private const SECRET = 'feature-webhook-secret-at-least-32-bytes';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.zeptomail.webhook_secret', self::SECRET);
    }

    public function test_authenticated_events_are_idempotent_and_keep_the_earliest_milestone(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        Queue::fake();
        $this->actingAs($owner)->post(
            route('quotes.deliveries.store', [$company, $quote]),
            $this->deliveryPayload($quote),
        );
        $delivery = $this->tenant($company, fn (): EmailDelivery => EmailDelivery::query()->sole());
        $this->executeDeliveryJob($company->id, $delivery->id, new WebhookAcceptedProvider);
        $reference = $this->tenant(
            $company,
            fn (): string => (string) EmailDeliveryAttempt::query()->sole()->client_reference,
        );

        $later = CarbonImmutable::now('UTC')->startOfSecond()->subMinute();
        $this->postWebhook($this->payload($reference, 'event-later', 'email_open', $later))
            ->assertAccepted()->assertJson(['accepted' => true]);
        $earlier = $later->subMinute();
        $response = $this->postWebhook($this->payload($reference, 'event-earlier', 'email_open', $earlier));
        $response->assertAccepted();
        $this->postWebhook($this->payload($reference, 'event-earlier', 'email_open', $earlier))
            ->assertAccepted();

        $this->tenant($company, function () use ($earlier): void {
            $this->assertCount(2, EmailProviderEvent::query()->get());
            $this->assertSame(
                $earlier->getTimestamp(),
                EmailDelivery::query()->sole()->opened_at->getTimestamp(),
            );
            $audits = AuditEvent::query()
                ->where('action', 'company.document.delivery.provider_event_recorded')->get();
            $this->assertCount(2, $audits);
            $this->assertSame(
                [AuditActorType::ProviderWebhook, AuditActorType::ProviderWebhook],
                $audits->pluck('actor_type')->all(),
            );
            $encoded = json_encode($audits->toArray(), JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('event-earlier', $encoded);
            $this->assertStringNotContainsString('email_open', $encoded);

            try {
                EmailProviderEvent::query()->firstOrFail()->update(['event_type' => 'CLICKED']);
                $this->fail('A normalized provider event was changed.');
            } catch (QueryException $exception) {
                $this->assertSame('23001', $exception->errorInfo[0] ?? null);
            }
        });
        [, $otherCompany] = $this->company('Other Webhook Company');
        $this->tenant(
            $otherCompany,
            fn () => $this->assertSame(0, EmailProviderEvent::query()->count()),
        );
    }

    public function test_invalid_or_unmapped_webhooks_create_no_tenant_effect(): void
    {
        $data = $this->payload(
            '0198f45d-9e53-7b65-a631-7d98f6065f63',
            'unknown-attempt',
            'delivered',
            CarbonImmutable::now('UTC'),
        );

        $this->call('POST', route('webhooks.zeptomail'), [], [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_X_INVUMO_WEBHOOK_KEY' => 'wrong-webhook-key-at-least-32-bytes',
        ], 'data='.urlencode($data))->assertUnauthorized();

        $this->postWebhook($data)->assertAccepted();
        $this->assertDatabaseCount('email_provider_events', 0);
    }

    public function test_authenticated_empty_provider_verification_probes_are_reachable(): void
    {
        $server = ['HTTP_X_INVUMO_WEBHOOK_KEY' => self::SECRET];

        $this->call('GET', route('webhooks.zeptomail'), [], [], [], $server)
            ->assertOk()->assertJson(['accepted' => true]);
        $this->call('POST', route('webhooks.zeptomail'), [], [], [], $server)
            ->assertOk()->assertJson(['accepted' => true]);
        $this->call('GET', route('webhooks.zeptomail'))
            ->assertUnauthorized();
    }

    public function test_legacy_or_malformed_requests_cannot_bypass_the_static_header_boundary(): void
    {
        $payload = $this->payload(
            '0198f45d-9e53-7b65-a631-7d98f6065f63',
            'boundary-event',
            'delivered',
            CarbonImmutable::now('UTC'),
        );

        $this->call('POST', route('webhooks.zeptomail'), [], [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_PRODUCER_SIGNATURE' => 'ts=1787918400000;s=legacy;s-algorithm=HmacSHA256',
        ], 'data='.urlencode($payload))->assertUnauthorized();

        $this->call('POST', route('webhooks.zeptomail'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_INVUMO_WEBHOOK_KEY' => self::SECRET,
        ], $payload)->assertBadRequest();

        $this->call('POST', route('webhooks.zeptomail'), [], [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_X_INVUMO_WEBHOOK_KEY' => self::SECRET,
        ], 'data='.str_repeat('a', 131073))->assertBadRequest();
    }

    /** @return TestResponse<Response> */
    private function postWebhook(string $data): TestResponse
    {
        $raw = 'data='.urlencode($data);

        return $this->call('POST', route('webhooks.zeptomail'), [], [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_X_INVUMO_WEBHOOK_KEY' => self::SECRET,
        ], $raw);
    }

    private function payload(
        string $reference,
        string $identifier,
        string $type,
        CarbonImmutable $occurredAt,
    ): string {
        return json_encode([
            'event_name' => [$type],
            'webhook_request_id' => $identifier,
            'event_message' => [[
                'email_info' => ['client_reference' => $reference],
                'event_data' => [['details' => [['time' => $occurredAt->getTimestamp()]]]],
            ]],
        ], JSON_THROW_ON_ERROR);
    }
}

final class WebhookAcceptedProvider implements SendsProviderEmail
{
    public function send(ProviderDelivery $delivery): ProviderDeliveryResult
    {
        return ProviderDeliveryResult::accepted('provider-message');
    }
}
