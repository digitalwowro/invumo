<?php

namespace Tests\Feature\Modules\Delivery;

use App\Foundation\Delivery\EmailAttachmentMode;
use App\Modules\Delivery\Data\DocumentArtifactLimits;
use App\Modules\Delivery\Data\EmailDeliveryAttemptState;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Data\PublicDocumentToken;
use App\Modules\Delivery\Jobs\DeleteDocumentArtifactFiles;
use App\Modules\Delivery\Models\DocumentArtifact;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryAttempt;
use App\Modules\Delivery\Models\EmailDeliveryRecipient;
use App\Modules\Delivery\Models\EmailProviderEvent;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\DocumentDeliveryTestCase;
use Throwable;

final class DocumentDeliveryDatabaseTest extends DocumentDeliveryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-08-28 12:00:00 Europe/Bucharest');
        Storage::fake('document_artifacts_local');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_forced_rls_hides_every_delivery_row_from_another_company(): void
    {
        [$owner, $company] = $this->company();
        [, $other] = $this->company('Other RLS SRL');
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        $this->tenant($company, fn () => $this->history($quote, EmailDeliveryState::Accepted));

        $this->tenant($other, function (): void {
            $this->assertSame(0, EmailDelivery::query()->count());
            $this->assertSame(0, EmailDeliveryRecipient::query()->count());
            $this->assertSame(0, EmailDeliveryAttempt::query()->count());
            $this->assertSame(0, DocumentArtifact::query()->count());
        });
    }

    public function test_delivery_content_and_finalized_attempts_are_database_immutable(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        $this->tenant($company, fn () => $this->history($quote, EmailDeliveryState::Accepted));

        foreach ([
            fn () => EmailDelivery::query()->update(['subject' => 'Changed']),
            fn () => EmailDeliveryRecipient::query()->update(['email' => 'changed@example.com']),
            fn () => EmailDeliveryAttempt::query()->update(['state' => EmailDeliveryAttemptState::Unknown]),
        ] as $mutation) {
            try {
                $this->tenant($company, $mutation);
                $this->fail('Immutable delivery history was changed.');
            } catch (QueryException $exception) {
                $this->assertSame('23001', $exception->errorInfo[0] ?? null);
            }
        }
    }

    public function test_pending_delivery_blocks_a_later_document_version_change_at_commit(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        $this->tenant($company, fn () => $this->history(
            $quote,
            EmailDeliveryState::Queued,
            withAttempt: false,
        ));

        try {
            $this->tenant($company, fn () => Document::query()->whereKey($quote->id)->update([
                'edit_version' => $quote->edit_version + 1,
                'content_version' => $quote->content_version + 1,
            ]));
            $this->fail('A document changed while its delivery was pending.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('SQLSTATE[23514]', $exception->getMessage());
        }
    }

    public function test_database_allows_only_one_pending_provider_attempt_per_delivery(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        $this->tenant($company, function () use ($quote): void {
            $delivery = $this->history($quote, EmailDeliveryState::Queued, withAttempt: false);
            $pending = [
                'delivery_id' => $delivery->id,
                'attempt_number' => 1,
                'client_reference' => (string) Str::uuid7(),
                'state' => EmailDeliveryAttemptState::Pending,
                'submitted_at' => now(),
            ];
            EmailDeliveryAttempt::query()->create($pending);

            try {
                EmailDeliveryAttempt::query()->create([
                    ...$pending,
                    'attempt_number' => 2,
                    'client_reference' => (string) Str::uuid7(),
                ]);
                $this->fail('A second pending provider attempt was accepted.');
            } catch (QueryException $exception) {
                $this->assertSame('23505', $exception->errorInfo[0] ?? null);
            }
        });
    }

    public function test_database_artifact_limit_matches_the_provider_safe_application_bound(): void
    {
        $definition = DB::connection(config('database.tenant_connection'))->selectOne(<<<'SQL'
            SELECT pg_get_constraintdef(oid) AS definition
            FROM pg_constraint
            WHERE conname = 'document_artifacts_file_check'
            SQL)?->definition;

        $this->assertIsString($definition);
        $this->assertStringContainsString(
            'byte_size >= 1',
            $definition,
        );
        $this->assertStringContainsString(
            'byte_size <= '.DocumentArtifactLimits::MAX_BYTES,
            $definition,
        );
    }

    public function test_database_rejects_duplicate_or_missing_to_recipients(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        $this->tenant($company, fn () => $this->history($quote, EmailDeliveryState::Accepted));

        foreach ([
            fn () => EmailDeliveryRecipient::query()->create([
                'delivery_id' => EmailDelivery::query()->sole()->id,
                'role' => 'CC',
                'email' => 'private@example.com',
                'display_order' => 2,
            ]),
            fn () => EmailDeliveryRecipient::query()->delete(),
        ] as $mutation) {
            try {
                $this->tenant($company, $mutation);
                $this->fail('Invalid delivery recipients were accepted.');
            } catch (Throwable $exception) {
                $this->assertStringContainsString('SQLSTATE[23514]', $exception->getMessage());
            }
        }
    }

    public function test_quote_deletion_erases_private_delivery_data_but_retains_operational_fact(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        Storage::disk('document_artifacts_local')->put('history.pdf', '%PDF retained test');
        $this->tenant($company, function () use ($quote): void {
            $delivery = $this->history($quote, EmailDeliveryState::Rejected, withArtifact: true);
            EmailProviderEvent::query()->create([
                'delivery_id' => $delivery->id,
                'provider_name' => 'ZEPTOMAIL',
                'provider_event_identifier' => 'private-provider-event',
                'event_type' => 'HARD_BOUNCED',
                'occurred_at' => now(),
                'received_at' => now(),
            ]);
        });
        Queue::fake();

        $this->actingAs($owner)->delete(route('quotes.destroy', [$company, $quote]), [
            'confirmed' => true,
            'confirmed_high_risk' => true,
            'deletion_state' => $this->quoteDeletionState($company, $quote),
        ])->assertRedirect()->assertSessionHas('status');

        $this->tenant($company, function (): void {
            $delivery = EmailDelivery::query()->sole();
            $attempt = EmailDeliveryAttempt::query()->sole();
            $this->assertNull($delivery->document_id);
            $this->assertNull($delivery->subject);
            $this->assertNull($delivery->button_url);
            $this->assertNull($delivery->provider_message_identifier);
            $this->assertNull($delivery->failure_summary);
            $this->assertSame('provider_permanent_rejection', $delivery->failure_category);
            $this->assertNotNull($delivery->redacted_at);
            $this->assertSame(0, EmailDeliveryRecipient::query()->count());
            $this->assertSame(0, DocumentArtifact::query()->count());
            $this->assertSame(EmailDeliveryAttemptState::PermanentRejection, $attempt->state);
            $this->assertNull($attempt->client_reference);
            $this->assertNull($attempt->provider_message_identifier);
            $this->assertNull($attempt->failure_summary);
            $this->assertSame('provider_permanent_rejection', $attempt->failure_category);
            $this->assertNotNull($attempt->redacted_at);
            $providerEvent = EmailProviderEvent::query()->sole();
            $this->assertNull($providerEvent->provider_name);
            $this->assertNull($providerEvent->provider_event_identifier);
            $this->assertNotNull($providerEvent->redacted_at);
        });
        Queue::assertPushed(DeleteDocumentArtifactFiles::class, 1);
    }

    private function history(
        Document $document,
        EmailDeliveryState $state,
        bool $withArtifact = false,
        bool $withAttempt = true,
    ): EmailDelivery {
        $token = PublicDocumentToken::fromBytes(random_bytes(PublicDocumentToken::BYTES));
        $link = PublicDocumentLink::query()->create([
            'document_id' => $document->id,
            'generation' => 1,
            'token_hash' => $token->hash,
            'token_ciphertext' => $token->plainText,
            'expires_at' => now()->addDays(30),
        ]);
        $artifact = $withArtifact ? DocumentArtifact::query()->create([
            'document_id' => $document->id,
            'artifact_type' => 'PDF_ATTACHMENT',
            'document_edit_version' => $document->edit_version,
            'storage_disk' => 'document_artifacts_local',
            'storage_key' => 'history.pdf',
            'file_name' => 'quote.pdf',
            'mime_type' => 'application/pdf',
            'byte_size' => 18,
            'sha256' => hash('sha256', '%PDF retained test'),
            'generated_at' => now(),
        ]) : null;
        $failed = $state === EmailDeliveryState::Rejected;
        $delivery = EmailDelivery::query()->create([
            'document_id' => $document->id,
            'public_document_link_id' => $link->id,
            'document_kind' => DocumentKind::Quote,
            'event_type' => EmailTemplateEvent::QuoteSent,
            'delivery_key' => (string) Str::uuid7(),
            'document_edit_version' => $document->edit_version,
            'language_code' => 'en',
            'subject' => 'Private subject',
            'body' => 'Private body',
            'button_label' => 'View',
            'signature' => 'Private signature',
            'button_url' => 'https://app.invumo.test/q/private-token',
            'attachment_mode' => $withArtifact
                ? EmailAttachmentMode::AttachPdf : EmailAttachmentMode::SecureLinkOnly,
            'artifact_id' => $artifact?->id,
            'provider_name' => 'ZEPTOMAIL',
            'dispatch_state' => $state,
            'provider_message_identifier' => $state === EmailDeliveryState::Queued
                ? null : 'provider-private-id',
            'failure_category' => $failed ? 'provider_permanent_rejection' : null,
            'failure_summary' => $failed ? 'Private diagnostic.' : null,
            'failed_at' => $failed ? now() : null,
            'accepted_at' => $state === EmailDeliveryState::Accepted ? now() : null,
        ]);
        EmailDeliveryRecipient::query()->create([
            'delivery_id' => $delivery->id,
            'role' => 'TO',
            'name' => 'Private Name',
            'email' => 'private@example.com',
            'display_order' => 1,
        ]);
        if ($withAttempt) {
            EmailDeliveryAttempt::query()->create([
                'delivery_id' => $delivery->id,
                'attempt_number' => 1,
                'client_reference' => (string) Str::uuid7(),
                'state' => $failed
                    ? EmailDeliveryAttemptState::PermanentRejection : EmailDeliveryAttemptState::Accepted,
                'provider_message_identifier' => 'attempt-provider-id',
                'failure_category' => $failed ? 'provider_permanent_rejection' : null,
                'failure_summary' => $failed ? 'Private attempt diagnostic.' : null,
                'submitted_at' => now(),
                'completed_at' => now(),
            ]);
        }

        return $delivery;
    }
}
