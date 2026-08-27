<?php

namespace App\Modules\Delivery\Http\Requests;

use App\Modules\Delivery\Support\PublicDocumentRequestToken;
use App\Modules\Quotes\Data\PublicQuoteDecision;
use App\Modules\Quotes\Data\PublicQuoteDecisionData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PublicQuoteDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(PublicQuoteDecision::class)],
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_email' => ['required', 'string', 'email:rfc', 'max:254'],
            'idempotency_key' => ['required', 'uuid'],
            'locale' => ['required', 'string', Rule::in(config('localization.supported_locales'))],
        ];
    }

    public function decision(): PublicQuoteDecisionData
    {
        return new PublicQuoteDecisionData(
            decision: PublicQuoteDecision::from((string) $this->validated('decision')),
            customerName: (string) $this->validated('customer_name'),
            customerEmail: (string) $this->validated('customer_email'),
            idempotencyKey: (string) $this->validated('idempotency_key'),
        );
    }

    protected function prepareForValidation(): void
    {
        $locale = trim((string) $this->input('locale'));

        if (in_array($locale, config('localization.supported_locales'), true)) {
            app()->setLocale($locale);
        }

        $this->merge([
            'decision' => strtoupper(trim((string) $this->input('decision'))),
            'customer_name' => trim((string) $this->input('customer_name')),
            'customer_email' => mb_strtolower(trim((string) $this->input('customer_email'))),
            'idempotency_key' => trim((string) $this->input('idempotency_key')),
            'locale' => $locale,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return route('public-quotes.show', [
            'token' => PublicDocumentRequestToken::plainText($this),
        ], false);
    }
}
