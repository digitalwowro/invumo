<?php

namespace Tests\Feature\Modules\Delivery;

use App\Foundation\Delivery\EmailAttachmentMode;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Delivery\Actions\RevokePublicDocumentLink;
use App\Modules\Delivery\Data\EmailDeliveryAttemptState;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Jobs\SendDocumentDelivery;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryAttempt;
use App\Modules\Delivery\Models\EmailDeliveryRecipient;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Invoices\Data\InvoiceLifecycle;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Models\Quote;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\DocumentDeliveryTestCase;

final class DocumentDeliveryHttpTest extends DocumentDeliveryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-08-28 12:00:00 Europe/Bucharest');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_member_reviews_and_queues_a_complete_quote_without_external_io(): void
    {
        [$owner, $company] = $this->company();
        $member = User::factory()->create();
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        Queue::fake();
        $this->actingAs($member)
            ->get(route('quotes.edit', [$company, $quote]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('directDelivery.composer.recipients.0.email', 'ana@example.com')
                ->where('directDelivery.composer.attachmentMode', 'SECURE_LINK_ONLY')
                ->where('directDelivery.history', [])
                ->where('deliveryTranslations.composer.submit', 'Send email'));

        $payload = $this->deliveryPayload($quote);
        $this->post(route('quotes.deliveries.store', [$company, $quote]), $payload)
            ->assertRedirect()
            ->assertSessionHas('status', 'Document email queued.');
        $second = $this->deliveryPayload($quote);
        $this->post(route('quotes.deliveries.store', [$company, $quote]), $second)
            ->assertSessionHasErrors('delivery');

        $this->tenant($company, function (): void {
            $delivery = EmailDelivery::query()->sole();
            $recipient = EmailDeliveryRecipient::query()->sole();
            $audit = AuditEvent::query()->where('action', 'company.document.delivery.queued')->sole();
            $serialized = json_encode($audit->toArray(), JSON_THROW_ON_ERROR);

            $this->assertSame(EmailDeliveryState::Queued, $delivery->dispatch_state);
            $this->assertSame(QuoteLifecycle::Draft, Quote::query()->sole()->lifecycle);
            $this->assertSame('ana@example.com', $recipient->email);
            $this->assertStringNotContainsString('{{public_url}}', (string) $delivery->body);
            $this->assertStringContainsString('/q/', (string) $delivery->button_url);
            $this->assertSame(1, PublicDocumentLink::query()->count());
            $this->assertSame(1, $audit->after['recipient_count']);
            $this->assertStringNotContainsString('ana@example.com', $serialized);
            $this->assertStringNotContainsString((string) $delivery->button_url, $serialized);
        });
        Queue::assertPushed(SendDocumentDelivery::class, 1);
    }

    public function test_sending_an_invoice_issues_it_before_queueing_the_delivery(): void
    {
        [$owner, $company] = $this->company();
        $invoice = $this->completeInvoice($company, $this->invoice($company, $owner));
        Queue::fake();

        $this->actingAs($owner)
            ->post(route('invoices.deliveries.store', [$company, $invoice]), $this->deliveryPayload(
                $invoice,
                EmailAttachmentMode::AttachPdf,
            ))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->tenant($company, function (): void {
            $document = EmailDelivery::query()->sole();
            $this->assertSame(EmailAttachmentMode::AttachPdf, $document->attachment_mode);
            $this->assertSame(InvoiceLifecycle::Issued, Invoice::query()->sole()->lifecycle);
            $this->assertSame(2, $document->document_edit_version);
        });
    }

    public function test_validation_and_sendability_fail_without_creating_history(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->quote($company, $owner);
        Queue::fake();
        $payload = $this->deliveryPayload($quote);
        $payload['subject'] = "Invalid\nsubject";
        $payload['body'] = 'Unknown {{customer_secret}}';
        $payload['recipients'][] = [
            'role' => 'CC', 'name' => null, 'email' => 'ana@example.com',
        ];

        $this->actingAs($owner)
            ->post(route('quotes.deliveries.store', [$company, $quote]), $payload)
            ->assertSessionHasErrors(['subject', 'body', 'recipients']);

        $valid = $this->deliveryPayload($quote);
        $this->post(route('quotes.deliveries.store', [$company, $quote]), $valid)
            ->assertSessionHasErrors('delivery');
        $this->assertSame(0, $this->tenant($company, fn (): int => EmailDelivery::query()->count()));
        Queue::assertNothingPushed();
    }

    public function test_resolved_public_url_cannot_overflow_persisted_content_limits(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        Queue::fake();
        $payload = $this->deliveryPayload($quote);
        $payload['button_label'] = str_repeat('x', 66).'{{public_url}}';

        $this->actingAs($owner)
            ->post(route('quotes.deliveries.store', [$company, $quote]), $payload)
            ->assertSessionHasErrors('button_label');

        $this->assertSame(0, $this->tenant($company, fn (): int => EmailDelivery::query()->count()));
        Queue::assertNothingPushed();
    }

    public function test_final_quote_resend_needs_confirmation_and_idempotency_is_document_scoped(): void
    {
        [$owner, $company] = $this->company();
        $first = $this->completeQuote($company, $this->quote($company, $owner));
        $second = $this->completeQuote($company, $this->quote($company, $owner));
        $this->tenant($company, fn () => Quote::query()->whereKey($first->id)->update([
            'lifecycle' => QuoteLifecycle::Accepted,
        ]));
        Queue::fake();
        $key = (string) Str::uuid7();
        $payload = $this->deliveryPayload($first, key: $key);

        $this->actingAs($owner)
            ->post(route('quotes.deliveries.store', [$company, $first]), $payload)
            ->assertSessionHasErrors('delivery');
        $payload['confirmed_final_quote_state'] = true;
        $this->post(route('quotes.deliveries.store', [$company, $first]), $payload)
            ->assertRedirect();
        $this->post(route('quotes.deliveries.store', [$company, $first]), $payload)
            ->assertRedirect();
        $payload['edit_version'] = $second->edit_version;
        $this->post(route('quotes.deliveries.store', [$company, $second]), $payload)
            ->assertSessionHasErrors('delivery');

        $this->assertSame(1, $this->tenant($company, fn (): int => EmailDelivery::query()->count()));
        Queue::assertPushed(SendDocumentDelivery::class, 1);
    }

    public function test_cross_company_document_is_hidden_and_creates_no_delivery(): void
    {
        [$owner, $company] = $this->company();
        [$otherOwner, $other] = $this->company('Other Delivery SRL');
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        Queue::fake();

        $this->actingAs($otherOwner)
            ->post(route('quotes.deliveries.store', [$other, $quote]), $this->deliveryPayload($quote))
            ->assertNotFound();
        $this->assertSame(0, $this->tenant($company, fn (): int => EmailDelivery::query()->count()));
    }

    public function test_a_revoked_delivery_link_removes_retry_and_fails_closed(): void
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
            $delivery->update([
                'dispatch_state' => EmailDeliveryState::Rejected,
                'failure_category' => 'provider_permanent_rejection',
                'failure_summary' => 'Rejected.',
                'failed_at' => now(),
            ]);

            return $delivery;
        });
        app(RevokePublicDocumentLink::class)->handle(
            $company,
            $owner,
            $quote->id,
            DocumentKind::Quote,
        );

        $this->get(route('quotes.edit', [$company, $quote]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('directDelivery.history.0.retryUrl', null));
        $this->post(
            route('quotes.deliveries.retry', [$company, $quote, $delivery]),
            ['confirmed' => true],
        )->assertSessionHasErrors('delivery');
    }

    public function test_public_quote_decision_waits_for_a_pending_delivery(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        Queue::fake();
        $this->actingAs($owner)->post(
            route('quotes.deliveries.store', [$company, $quote]),
            $this->deliveryPayload($quote),
        )->assertRedirect();
        $token = $this->tenant(
            $company,
            fn (): string => PublicDocumentLink::query()->sole()->token_ciphertext,
        );

        $this->post(route('public-quotes.decision', $token), [
            'decision' => 'ACCEPTED',
            'customer_name' => 'Ana Popescu',
            'customer_email' => 'ana@example.com',
            'idempotency_key' => (string) Str::uuid7(),
            'locale' => 'en',
        ])->assertSessionHasErrors([
            'decision' => 'This quote is currently being sent. Wait a moment, then try again.',
        ]);
    }

    public function test_document_deletion_waits_while_provider_submission_is_in_flight(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        Queue::fake();
        $this->actingAs($owner)->post(
            route('quotes.deliveries.store', [$company, $quote]),
            $this->deliveryPayload($quote),
        );
        $this->tenant($company, function (): void {
            $delivery = EmailDelivery::query()->sole();
            EmailDeliveryAttempt::query()->create([
                'delivery_id' => $delivery->id,
                'attempt_number' => 1,
                'client_reference' => (string) Str::uuid7(),
                'state' => EmailDeliveryAttemptState::Pending,
                'submitted_at' => now(),
            ]);
        });

        $this->get(route('quotes.edit', [$company, $quote]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('deletion.guard.blocked', true)
                ->where('deletion.guard.description', 'Linked Invoices: 0. Provider submissions still in progress: 1.'));

        $this->delete(route('quotes.destroy', [$company, $quote]), [
            'confirmed' => true,
            'confirmed_high_risk' => true,
            'deletion_state' => $this->quoteDeletionState($company, $quote),
        ])->assertSessionHasErrors([
            'quote' => 'Wait for the active email submission to finish before deleting this Quote.',
        ]);

        $this->assertNotNull($this->tenant(
            $company,
            fn () => Quote::query()->find($quote->id),
        ));
    }
}
