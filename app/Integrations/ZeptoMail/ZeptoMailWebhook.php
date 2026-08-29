<?php

namespace App\Integrations\ZeptoMail;

use App\Modules\Delivery\Contracts\ParsesProviderWebhook;
use App\Modules\Delivery\Contracts\ProviderWebhookRequestException;
use App\Modules\Delivery\Data\ProviderWebhookEvent;
use App\Modules\Delivery\Data\ProviderWebhookEventType;
use Carbon\CarbonImmutable;
use JsonException;

final readonly class ZeptoMailWebhook implements ParsesProviderWebhook
{
    private const MAX_BODY_BYTES = 131072;

    public function parse(
        string $rawBody,
        ?string $authenticationKey,
        ?string $contentType,
        CarbonImmutable $receivedAt,
    ): ?ProviderWebhookEvent {
        $this->authenticate($authenticationKey);

        if ($rawBody === '') {
            return null;
        }

        $data = $this->data($rawBody, $contentType);

        try {
            $payload = json_decode($data, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ProviderWebhookRequestException::malformed();
        }

        if (! is_array($payload)) {
            throw ProviderWebhookRequestException::malformed();
        }

        return $this->event($payload, $receivedAt);
    }

    private function data(string $rawBody, ?string $contentType): string
    {
        if ($rawBody === '' || strlen($rawBody) > self::MAX_BODY_BYTES) {
            throw ProviderWebhookRequestException::malformed();
        }

        $normalized = strtolower(trim(explode(';', (string) $contentType)[0]));

        if ($normalized === 'application/json') {
            return $rawBody;
        }

        if ($normalized !== 'application/x-www-form-urlencoded'
            || preg_match('/^data=([^&]*)$/s', $rawBody, $matches) !== 1
            || preg_match('/%(?![0-9A-Fa-f]{2})/', $matches[1]) === 1) {
            throw ProviderWebhookRequestException::malformed();
        }

        $decoded = urldecode($matches[1]);

        if ($decoded === '' || strlen($decoded) > self::MAX_BODY_BYTES) {
            throw ProviderWebhookRequestException::malformed();
        }

        return $decoded;
    }

    private function authenticate(?string $provided): void
    {
        $secret = config('services.zeptomail.webhook_secret');

        if (! is_string($secret) || strlen($secret) < 32 || strlen($secret) > 512
            || ! is_string($provided)
            || ! hash_equals(hash('sha256', $secret, true), hash('sha256', $provided, true))) {
            throw ProviderWebhookRequestException::unauthorized();
        }
    }

    /** @param array<string, mixed> $payload */
    private function event(array $payload, CarbonImmutable $receivedAt): ?ProviderWebhookEvent
    {
        $providerName = $this->firstString($payload['event_name'] ?? null);
        $type = match ($providerName) {
            'delivered', 'email_delivery', 'email_delivered' => ProviderWebhookEventType::Delivered,
            'softbounce' => ProviderWebhookEventType::SoftBounced,
            'hardbounce' => ProviderWebhookEventType::HardBounced,
            'email_open' => ProviderWebhookEventType::Opened,
            'email_link_click' => ProviderWebhookEventType::Clicked,
            'feedback_loop', 'fbl_complaint' => ProviderWebhookEventType::FeedbackLoop,
            default => null,
        };

        if ($type === null) {
            return null;
        }

        $message = $this->firstArray($payload['event_message'] ?? null);
        $emailInfo = is_array($message['email_info'] ?? null) ? $message['email_info'] : [];
        $eventIdentifier = $payload['webhook_request_id'] ?? $message['webhook_request_id'] ?? null;
        $clientReference = $emailInfo['client_reference'] ?? null;

        if (! is_string($clientReference)
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $clientReference) !== 1) {
            return null;
        }

        if (! is_string($eventIdentifier)
            || preg_match('/^[A-Za-z0-9._:-]{1,160}$/', $eventIdentifier) !== 1) {
            throw ProviderWebhookRequestException::malformed();
        }

        $occurredAt = $this->occurredAt($message, $emailInfo, $receivedAt);

        if ($occurredAt->greaterThan($receivedAt->addMinutes(5))) {
            throw ProviderWebhookRequestException::malformed();
        }

        return new ProviderWebhookEvent(
            $eventIdentifier,
            strtolower($clientReference),
            $type,
            $occurredAt,
            $receivedAt,
        );
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $emailInfo
     */
    private function occurredAt(
        array $message,
        array $emailInfo,
        CarbonImmutable $fallback,
    ): CarbonImmutable {
        $eventData = $this->firstArray($message['event_data'] ?? null);
        $details = $this->firstArray($eventData['details'] ?? null);
        $value = $details['time'] ?? $details['modified_time'] ?? $emailInfo['processed_time'] ?? null;

        try {
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $number = (int) $value;

                return $number >= 100000000000
                    ? CarbonImmutable::createFromTimestampMsUTC($number)
                    : CarbonImmutable::createFromTimestamp($number, 'UTC');
            }

            return is_string($value) ? CarbonImmutable::parse($value)->utc() : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function firstString(mixed $value): ?string
    {
        return is_array($value) && is_string($value[0] ?? null) ? $value[0] : null;
    }

    /** @return array<string, mixed> */
    private function firstArray(mixed $value): array
    {
        return is_array($value) && is_array($value[0] ?? null) ? $value[0] : [];
    }
}
