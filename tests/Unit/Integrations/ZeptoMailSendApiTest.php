<?php

namespace Tests\Unit\Integrations;

use App\Integrations\ZeptoMail\ZeptoMailSendApi;
use App\Modules\Customers\Data\DeliveryRecipientRole;
use App\Modules\Delivery\Data\EmailDeliveryAttemptState;
use App\Modules\Delivery\Data\EmailRecipientData;
use App\Modules\Delivery\Data\ProviderDelivery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ZeptoMailSendApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'services.zeptomail.endpoint' => 'https://api.zeptomail.eu/v1.1/email',
            'services.zeptomail.token' => 'test-api-token',
            'services.zeptomail.timeout' => 20,
            'services.zeptomail.connect_timeout' => 5,
            'mail.from.address' => 'hello@invumo.test',
            'mail.from.name' => 'Invumo',
        ]);
    }

    public function test_it_sends_the_approved_payload_and_accepts_a_successful_response(): void
    {
        Http::fake(['*' => Http::response(['request_id' => 'provider-request'], 201)]);

        $result = app(ZeptoMailSendApi::class)->send($this->delivery());

        $this->assertSame(EmailDeliveryAttemptState::Accepted, $result->state);
        $this->assertSame('provider-request', $result->providerMessageIdentifier);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.zeptomail.eu/v1.1/email'
                && $request->hasHeader('Authorization', 'Zoho-enczapikey test-api-token')
                && $payload['client_reference'] === '0198f45d-9e53-7b65-a631-7d98f6065f63'
                && $payload['to'][0]['email_address']['address'] === 'to@example.com'
                && $payload['bcc'][0]['email_address']['address'] === 'bcc@example.com'
                && base64_decode($payload['attachments'][0]['content'], true) === '%PDF bytes'
                && $payload['track_opens'] === true
                && $payload['track_clicks'] === true;
        });
    }

    #[DataProvider('rejectedResponses')]
    public function test_it_distinguishes_temporary_and_permanent_provider_rejections(
        int $status,
        EmailDeliveryAttemptState $expected,
        string $category,
    ): void {
        Http::fake(['*' => Http::response([], $status)]);

        $result = app(ZeptoMailSendApi::class)->send($this->delivery());

        $this->assertSame($expected, $result->state);
        $this->assertSame($category, $result->failureCategory);
        $this->assertNull($result->providerMessageIdentifier);
    }

    /** @return iterable<string, array{int, EmailDeliveryAttemptState, string}> */
    public static function rejectedResponses(): iterable
    {
        yield 'rate limit' => [429, EmailDeliveryAttemptState::RetryableRejection, 'provider_temporary_rejection'];
        yield 'server rejection' => [503, EmailDeliveryAttemptState::RetryableRejection, 'provider_temporary_rejection'];
        yield 'invalid request' => [400, EmailDeliveryAttemptState::PermanentRejection, 'provider_permanent_rejection'];
    }

    public function test_transport_failure_is_unknown_and_never_classified_for_automatic_retry(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $result = app(ZeptoMailSendApi::class)->send($this->delivery());

        $this->assertSame(EmailDeliveryAttemptState::Unknown, $result->state);
        $this->assertSame('ambiguous_transmission', $result->failureCategory);
        $this->assertStringContainsString('timed out', (string) $result->failureSummary);
    }

    private function delivery(): ProviderDelivery
    {
        return new ProviderDelivery(
            clientReference: '0198f45d-9e53-7b65-a631-7d98f6065f63',
            language: 'en',
            recipients: [
                new EmailRecipientData(DeliveryRecipientRole::To, 'To Name', 'to@example.com', 1),
                new EmailRecipientData(DeliveryRecipientRole::Bcc, null, 'bcc@example.com', 2),
            ],
            subject: 'Invoice',
            textBody: 'Plain body',
            htmlBody: '<p>HTML body</p>',
            attachmentName: 'invoice.pdf',
            attachmentBytes: '%PDF bytes',
        );
    }
}
