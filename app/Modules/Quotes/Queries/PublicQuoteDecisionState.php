<?php

namespace App\Modules\Quotes\Queries;

use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Data\ResolvedPublicDocument;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Models\Quote;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

final readonly class PublicQuoteDecisionState
{
    /** @return array{state: string, submitUrl: string|null, idempotencyKey: string|null, locale: string, csrfToken: string, customerName: string, customerEmail: string} */
    public function for(ResolvedPublicDocument $resolved, string $token): array
    {
        $quote = Quote::query()->whereKey($resolved->document->id)->firstOrFail();
        $locale = $resolved->document->document_language ?? 'en';
        $state = match ($quote->lifecycle) {
            QuoteLifecycle::Accepted => 'ACCEPTED',
            QuoteLifecycle::Rejected => 'REJECTED',
            QuoteLifecycle::Sent => $this->isExpired($resolved, $quote)
                ? 'UNAVAILABLE'
                : 'AVAILABLE',
            QuoteLifecycle::Draft => 'UNAVAILABLE',
        };

        return [
            'state' => $state,
            'submitUrl' => $state === 'AVAILABLE'
                ? route('public-quotes.decision', ['token' => $token], false)
                : null,
            'idempotencyKey' => $state === 'AVAILABLE' ? (string) Str::uuid7() : null,
            'locale' => $locale,
            'csrfToken' => csrf_token(),
            'customerName' => $this->oldString('customer_name'),
            'customerEmail' => $this->oldString('customer_email'),
        ];
    }

    private function isExpired(ResolvedPublicDocument $resolved, Quote $quote): bool
    {
        if ($quote->valid_until === null) {
            return false;
        }

        $timezone = CompanySetting::query()->value('timezone') ?? 'UTC';

        return Date::now($timezone)->toImmutable()->startOfDay()->greaterThan($quote->valid_until);
    }

    private function oldString(string $key): string
    {
        $value = old($key, '');

        return is_string($value) ? $value : '';
    }
}
