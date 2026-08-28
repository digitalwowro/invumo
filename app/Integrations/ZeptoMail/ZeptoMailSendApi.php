<?php

namespace App\Integrations\ZeptoMail;

use App\Modules\Customers\Data\DeliveryRecipientRole;
use App\Modules\Delivery\Contracts\SendsProviderEmail;
use App\Modules\Delivery\Data\EmailRecipientData;
use App\Modules\Delivery\Data\ProviderDelivery;
use App\Modules\Delivery\Data\ProviderDeliveryResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

final readonly class ZeptoMailSendApi implements SendsProviderEmail
{
    public function send(ProviderDelivery $delivery): ProviderDeliveryResult
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Zoho-enczapikey '.(string) config('services.zeptomail.token'),
                'Accept' => 'application/json',
            ])->connectTimeout((int) config('services.zeptomail.connect_timeout'))
                ->timeout((int) config('services.zeptomail.timeout'))
                ->post((string) config('services.zeptomail.endpoint'), $this->payload($delivery));
        } catch (ConnectionException $exception) {
            return ProviderDeliveryResult::unknown($this->safeTransportSummary($exception));
        } catch (Throwable) {
            return ProviderDeliveryResult::unknown('The provider transmission outcome could not be confirmed.');
        }

        if ($response->successful()) {
            $identifier = $response->json('request_id');

            return ProviderDeliveryResult::accepted(
                is_string($identifier) && mb_strlen($identifier) <= 500
                    ? $identifier
                    : null,
            );
        }

        $retryable = $response->status() === 429 || $response->serverError();

        return ProviderDeliveryResult::rejected(
            $retryable,
            $retryable ? 'provider_temporary_rejection' : 'provider_permanent_rejection',
            $retryable
                ? 'The provider rejected the request temporarily.'
                : 'The provider rejected the request permanently.',
        );
    }

    /** @return array<string, mixed> */
    private function payload(ProviderDelivery $delivery): array
    {
        $payload = [
            'from' => [
                'address' => (string) config('mail.from.address'),
                'name' => (string) config('mail.from.name'),
            ],
            'to' => $this->recipients($delivery->recipients, DeliveryRecipientRole::To),
            'cc' => $this->recipients($delivery->recipients, DeliveryRecipientRole::Cc),
            'bcc' => $this->recipients($delivery->recipients, DeliveryRecipientRole::Bcc),
            'subject' => $delivery->subject,
            'textbody' => $delivery->textBody,
            'htmlbody' => $delivery->htmlBody,
            'track_opens' => true,
            'track_clicks' => true,
            'client_reference' => $delivery->clientReference,
        ];

        if ($delivery->attachmentBytes !== null && $delivery->attachmentName !== null) {
            $payload['attachments'] = [[
                'content' => base64_encode($delivery->attachmentBytes),
                'mime_type' => 'application/pdf',
                'name' => $delivery->attachmentName,
            ]];
        }

        return $payload;
    }

    /**
     * @param  list<EmailRecipientData>  $recipients
     * @return list<array{email_address: array{address: string, name?: string}}>
     */
    private function recipients(array $recipients, DeliveryRecipientRole $role): array
    {
        return array_values(array_map(
            static function (EmailRecipientData $recipient): array {
                $address = ['address' => $recipient->email];

                if ($recipient->name !== null) {
                    $address['name'] = $recipient->name;
                }

                return ['email_address' => $address];
            },
            array_filter($recipients, fn (EmailRecipientData $recipient): bool => $recipient->role === $role),
        ));
    }

    private function safeTransportSummary(ConnectionException $exception): string
    {
        return str_contains(strtolower($exception->getMessage()), 'timed out')
            ? 'The provider transmission timed out and its outcome is unknown.'
            : 'The provider connection failed and its transmission outcome is unknown.';
    }
}
