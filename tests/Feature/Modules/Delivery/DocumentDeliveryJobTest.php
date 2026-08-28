<?php

namespace Tests\Feature\Modules\Delivery;

use App\Foundation\Delivery\EmailAttachmentMode;
use App\Foundation\Tenancy\TenantContext;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Delivery\Contracts\RendersDocumentPdf;
use App\Modules\Delivery\Contracts\SendsProviderEmail;
use App\Modules\Delivery\Data\EmailDeliveryAttemptState;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\ProviderDelivery;
use App\Modules\Delivery\Data\ProviderDeliveryResult;
use App\Modules\Delivery\Exceptions\RetryableProviderRejection;
use App\Modules\Delivery\Jobs\SendDocumentDelivery;
use App\Modules\Delivery\Models\DocumentArtifact;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryAttempt;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Models\Quote;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\DocumentDeliveryTestCase;

final class DocumentDeliveryJobTest extends DocumentDeliveryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-08-28 12:00:00 Europe/Bucharest');
        Storage::fake('document_artifacts_local');
        config()->set('invumo.document_artifacts.disk', 'document_artifacts_local');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_provider_acceptance_sends_resolved_content_after_commit_and_marks_quote_sent(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        Queue::fake();
        $payload = $this->deliveryPayload($quote);
        $payload['body'] = 'Safe <script>alert(1)</script> {{public_url}}';
        $this->actingAs($owner)
            ->post(route('quotes.deliveries.store', [$company, $quote]), $payload)
            ->assertRedirect();
        $delivery = $this->tenant($company, fn (): EmailDelivery => EmailDelivery::query()->sole());
        $provider = new RecordingProvider(ProviderDeliveryResult::accepted('request-123'));

        $this->executeDeliveryJob($company->id, $delivery->id, $provider);

        $this->assertCount(1, $provider->deliveries);
        $outbound = $provider->deliveries[0];
        $this->assertNull($provider->tenantCompanyId);
        $this->assertSame(0, $provider->transactionLevel);
        $this->assertStringContainsString('&lt;script&gt;', $outbound->htmlBody);
        $this->assertStringNotContainsString('<script>', $outbound->htmlBody);
        $this->assertStringContainsString('/q/', $outbound->textBody);
        $this->assertSame('ana@example.com', $outbound->recipients[0]->email);
        $this->assertNull($outbound->attachmentBytes);
        $this->tenant($company, function (): void {
            $delivery = EmailDelivery::query()->sole();
            $attempt = EmailDeliveryAttempt::query()->sole();
            $this->assertSame(EmailDeliveryState::Accepted, $delivery->dispatch_state);
            $this->assertSame('request-123', $delivery->provider_message_identifier);
            $this->assertSame(EmailDeliveryAttemptState::Accepted, $attempt->state);
            $this->assertSame(QuoteLifecycle::Sent, Quote::query()->sole()->lifecycle);
        });
    }

    public function test_attached_pdf_is_persisted_once_and_exact_bytes_are_sent(): void
    {
        [$owner, $company] = $this->company();
        $invoice = $this->completeInvoice($company, $this->invoice($company, $owner));
        Queue::fake();
        $this->app->instance(RendersDocumentPdf::class, new FixedPdfRenderer('%PDF-1.7 immutable'));
        $this->actingAs($owner)->post(
            route('invoices.deliveries.store', [$company, $invoice]),
            $this->deliveryPayload($invoice, EmailAttachmentMode::AttachPdf),
        )->assertRedirect();
        $delivery = $this->tenant($company, fn (): EmailDelivery => EmailDelivery::query()->sole());
        $provider = new RecordingProvider(ProviderDeliveryResult::accepted(null));

        $this->executeDeliveryJob($company->id, $delivery->id, $provider);

        $this->assertSame('%PDF-1.7 immutable', $provider->deliveries[0]->attachmentBytes);
        $this->tenant($company, function (): void {
            $artifact = DocumentArtifact::query()->sole();
            $delivery = EmailDelivery::query()->sole();
            $this->assertSame($artifact->id, $delivery->artifact_id);
            $this->assertSame(hash('sha256', '%PDF-1.7 immutable'), $artifact->sha256);
            Storage::disk('document_artifacts_local')->assertExists($artifact->storage_key);
            $this->assertSame(
                '%PDF-1.7 immutable',
                Storage::disk('document_artifacts_local')->get($artifact->storage_key),
            );
        });
    }

    public function test_known_temporary_failure_retries_with_a_new_attempt_then_accepts(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        Queue::fake();
        $this->actingAs($owner)->post(
            route('quotes.deliveries.store', [$company, $quote]),
            $this->deliveryPayload($quote),
        );
        $delivery = $this->tenant($company, fn (): EmailDelivery => EmailDelivery::query()->sole());
        $temporary = new RecordingProvider(ProviderDeliveryResult::rejected(
            true,
            'provider_temporary_rejection',
            'Temporary.',
        ));

        try {
            $this->executeDeliveryJob($company->id, $delivery->id, $temporary);
            $this->fail('A retryable provider rejection did not request a queue retry.');
        } catch (RetryableProviderRejection) {
            $this->addToAssertionCount(1);
        }
        $accepted = new RecordingProvider(ProviderDeliveryResult::accepted('second-request'));
        $this->executeDeliveryJob($company->id, $delivery->id, $accepted, attempt: 2);

        $this->tenant($company, function (): void {
            $attempts = EmailDeliveryAttempt::query()->orderBy('attempt_number')->get();
            $this->assertCount(2, $attempts);
            $this->assertNotSame($attempts[0]->client_reference, $attempts[1]->client_reference);
            $this->assertSame(EmailDeliveryAttemptState::RetryableRejection, $attempts[0]->state);
            $this->assertSame(EmailDeliveryAttemptState::Accepted, $attempts[1]->state);
            $this->assertSame(EmailDeliveryState::Accepted, EmailDelivery::query()->sole()->dispatch_state);
        });
    }

    public function test_unknown_outcome_does_not_retry_and_manual_retry_is_explicit(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        Queue::fake();
        $this->actingAs($owner)->post(
            route('quotes.deliveries.store', [$company, $quote]),
            $this->deliveryPayload($quote),
        );
        $delivery = $this->tenant($company, fn (): EmailDelivery => EmailDelivery::query()->sole());
        $this->executeDeliveryJob($company->id, $delivery->id, new RecordingProvider(
            ProviderDeliveryResult::unknown('Unknown.'),
        ));

        $this->tenant($company, fn () => $this->assertSame(
            EmailDeliveryState::Unknown,
            EmailDelivery::query()->sole()->dispatch_state,
        ));
        Queue::fake();
        $this->actingAs($owner)
            ->post(route('quotes.deliveries.retry', [$company, $quote, $delivery]), ['confirmed' => false])
            ->assertSessionHasErrors('confirmed');
        $this->post(route('quotes.deliveries.retry', [$company, $quote, $delivery]), ['confirmed' => true])
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->tenant($company, fn () => $this->assertSame(
            EmailDeliveryState::Queued,
            EmailDelivery::query()->sole()->dispatch_state,
        ));
        Queue::assertPushed(SendDocumentDelivery::class, 1);

        $this->executeDeliveryJob(
            $company->id,
            $delivery->id,
            new RecordingProvider(ProviderDeliveryResult::accepted('manual-retry')),
        );
        $this->tenant($company, function (): void {
            $this->assertSame(EmailDeliveryState::Accepted, EmailDelivery::query()->sole()->dispatch_state);
            $this->assertSame(2, EmailDeliveryAttempt::query()->count());
            $this->assertSame(2, AuditEvent::query()
                ->where('action', 'company.document.delivery.completed')->count());
        });
    }

    public function test_exhausted_internal_failure_releases_the_document_with_safe_history(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        Queue::fake();
        $this->actingAs($owner)->post(
            route('quotes.deliveries.store', [$company, $quote]),
            $this->deliveryPayload($quote),
        );
        $delivery = $this->tenant($company, fn (): EmailDelivery => EmailDelivery::query()->sole());

        (new SendDocumentDelivery($company->id, $delivery->id))->failed(
            new RuntimeException('Private infrastructure detail'),
        );

        $this->tenant($company, function (): void {
            $delivery = EmailDelivery::query()->sole();
            $this->assertSame(EmailDeliveryState::Rejected, $delivery->dispatch_state);
            $this->assertSame('internal_delivery_failure', $delivery->failure_category);
            $audit = AuditEvent::query()
                ->where('action', 'company.document.delivery.completed')->sole();
            $this->assertStringNotContainsString(
                'Private infrastructure detail',
                json_encode($audit->toArray(), JSON_THROW_ON_ERROR),
            );
        });
    }

    public function test_failure_after_submission_started_is_recorded_as_unknown(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        Queue::fake();
        $this->actingAs($owner)->post(
            route('quotes.deliveries.store', [$company, $quote]),
            $this->deliveryPayload($quote),
        );
        $delivery = $this->tenant($company, function (): EmailDelivery {
            $delivery = EmailDelivery::query()->sole();
            EmailDeliveryAttempt::query()->create([
                'delivery_id' => $delivery->id,
                'attempt_number' => 1,
                'client_reference' => (string) Str::uuid7(),
                'state' => EmailDeliveryAttemptState::Pending,
                'submitted_at' => now(),
            ]);

            return $delivery;
        });

        (new SendDocumentDelivery($company->id, $delivery->id))->failed(
            new RuntimeException('Private infrastructure detail'),
        );

        $this->tenant($company, function (): void {
            $delivery = EmailDelivery::query()->sole();
            $attempt = EmailDeliveryAttempt::query()->sole();
            $this->assertSame(EmailDeliveryState::Unknown, $delivery->dispatch_state);
            $this->assertSame(EmailDeliveryAttemptState::Unknown, $attempt->state);
            $this->assertSame('ambiguous_transmission', $delivery->failure_category);
            $this->assertNotNull($attempt->completed_at);
        });
    }
}

final class RecordingProvider implements SendsProviderEmail
{
    /** @var list<ProviderDelivery> */
    public array $deliveries = [];

    public ?string $tenantCompanyId = null;

    public int $transactionLevel = -1;

    public function __construct(private readonly ProviderDeliveryResult $result) {}

    public function send(ProviderDelivery $delivery): ProviderDeliveryResult
    {
        $this->deliveries[] = $delivery;
        $this->tenantCompanyId = app(TenantContext::class)->companyId();
        $this->transactionLevel = app('db')->connection(config('database.tenant_connection'))->transactionLevel();

        return $this->result;
    }
}

final readonly class FixedPdfRenderer implements RendersDocumentPdf
{
    public function __construct(private string $bytes) {}

    public function render(string $html): string
    {
        return $this->bytes;
    }
}
