<?php

namespace Tests\Feature\Modules\Delivery;

use App\Modules\Delivery\Actions\CreatePublicDocumentLink;
use App\Modules\Delivery\Data\PublicDocumentToken;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Quotes\Models\QuotePublicDecision;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\PublicDocumentTestCase;

final class PublicDocumentRateLimitTest extends PublicDocumentTestCase
{
    public function test_named_limits_match_the_approved_buckets_without_plaintext_keys(): void
    {
        $request = Request::create('/q/redacted', server: ['REMOTE_ADDR' => '203.0.113.42']);
        $view = RateLimiter::limiter('public-document-view')?->__invoke($request);
        $pdf = RateLimiter::limiter('public-document-pdf')?->__invoke($request);
        $decision = RateLimiter::limiter('public-document-decision')?->__invoke($request);

        $this->assertSame([60, 120], array_column($view, 'maxAttempts'));
        $this->assertSame([10, 10], array_column($pdf, 'maxAttempts'));
        $this->assertSame([10, 5], array_column($decision, 'maxAttempts'));
        $this->assertSame([60, 60], array_column($view, 'decaySeconds'));
        $this->assertSame([60, 60], array_column($pdf, 'decaySeconds'));
        $this->assertSame([60, 60], array_column($decision, 'decaySeconds'));

        foreach ([
            ...array_column($view, 'key'),
            ...array_column($pdf, 'key'),
            ...array_column($decision, 'key'),
        ] as $key) {
            $this->assertStringNotContainsString('203.0.113.42', $key);
            $this->assertStringNotContainsString('redacted', $key);
        }
    }

    public function test_pdf_limit_returns_retry_after_without_creating_side_effects(): void
    {
        [$owner, $company] = $this->company();
        $invoice = $this->invoice($company, $owner);
        $link = app(CreatePublicDocumentLink::class)->handle(
            $company,
            $owner,
            $invoice->id,
            DocumentKind::Invoice,
        );
        $token = $link->token_ciphertext;

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->get(route('public-invoices.pdf', $token))->assertOk();
        }

        $this->get(route('public-invoices.pdf', $token))
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
        $this->assertSame(1, $this->tenant(
            $company,
            fn (): int => PublicDocumentLink::query()->count(),
        ));
        $this->assertSame(hash('sha256', $token), PublicDocumentToken::lookupHash($token));
    }

    public function test_decision_token_limit_blocks_the_sixth_attempt_without_side_effects(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->quote($company, $owner);
        $link = app(CreatePublicDocumentLink::class)->handle(
            $company,
            $owner,
            $quote->id,
            DocumentKind::Quote,
        );
        $payload = [
            'decision' => 'ACCEPTED',
            'customer_name' => '',
            'customer_email' => 'invalid',
            'idempotency_key' => 'invalid',
            'locale' => 'en',
        ];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('public-quotes.decision', $link->token_ciphertext), $payload)
                ->assertRedirect();
        }

        $this->post(route('public-quotes.decision', $link->token_ciphertext), $payload)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
        $this->assertSame(0, $this->tenant(
            $company,
            fn (): int => QuotePublicDecision::query()->count(),
        ));
    }
}
