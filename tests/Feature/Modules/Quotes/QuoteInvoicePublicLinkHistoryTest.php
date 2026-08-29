<?php

namespace Tests\Feature\Modules\Quotes;

use App\Foundation\Delivery\EmailAttachmentMode;
use App\Modules\Delivery\Actions\CreatePublicDocumentLink;
use App\Modules\Delivery\Actions\RevokePublicDocumentLink;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Data\PublicDocumentToken;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryRecipient;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Quotes\Models\QuoteInvoiceLink;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PublicDocumentTestCase;

final class QuoteInvoicePublicLinkHistoryTest extends PublicDocumentTestCase
{
    public function test_revoked_public_link_history_permanently_blocks_unlinking(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->quote($company, $owner);
        $invoice = $this->invoice($company, $owner);
        $this->tenant($company, fn () => QuoteInvoiceLink::query()->create([
            'quote_id' => $quote->id,
            'invoice_id' => $invoice->id,
            'copied_by_user_id' => $owner->id,
            'creation_key' => (string) Str::uuid7(),
            'copied_at' => now(),
        ]));
        app(CreatePublicDocumentLink::class)->handle(
            $company,
            $owner,
            $invoice->id,
            DocumentKind::Invoice,
        );
        app(RevokePublicDocumentLink::class)->handle(
            $company,
            $owner,
            $invoice->id,
            DocumentKind::Invoice,
        );

        $this->actingAs($owner)
            ->get(route('quotes.edit', [$company, $quote]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoiceAllocation.invoices.0.canUnlink', false)
                ->where('deletion.guard.blocked', true)
                ->where('deletion.guard.description', 'Linked Invoices: 1. Provider submissions still in progress: 0.'));
        $this->post(route('quotes.invoices.unlink', [$company, $quote, $invoice]), [
            'reason' => 'Revoked link should not matter',
            'confirmed' => true,
        ])->assertSessionHasErrors('unlink');

        $this->tenant($company, fn () => $this->assertSame(
            1,
            QuoteInvoiceLink::query()->count(),
        ));
    }

    public function test_delivery_history_permanently_blocks_unlinking_and_the_ui_matches(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->quote($company, $owner);
        $invoice = $this->invoice($company, $owner);
        $this->tenant($company, function () use ($quote, $invoice, $owner): void {
            QuoteInvoiceLink::query()->create([
                'quote_id' => $quote->id,
                'invoice_id' => $invoice->id,
                'copied_by_user_id' => $owner->id,
                'creation_key' => (string) Str::uuid7(),
                'copied_at' => now(),
            ]);
            $token = PublicDocumentToken::fromBytes(random_bytes(PublicDocumentToken::BYTES));
            $publicLink = PublicDocumentLink::query()->create([
                'document_id' => $invoice->id,
                'generation' => 1,
                'token_hash' => $token->hash,
                'token_ciphertext' => $token->plainText,
                'expires_at' => now()->addDays(30),
            ]);
            $delivery = EmailDelivery::query()->create([
                'document_id' => $invoice->id,
                'public_document_link_id' => $publicLink->id,
                'document_kind' => DocumentKind::Invoice,
                'event_type' => EmailTemplateEvent::InvoiceSent,
                'delivery_key' => (string) Str::uuid7(),
                'document_edit_version' => $invoice->edit_version,
                'language_code' => 'en',
                'subject' => 'Sent Invoice',
                'body' => 'Sent body',
                'button_label' => 'View',
                'button_url' => 'https://app.invumo.test/i/history',
                'attachment_mode' => EmailAttachmentMode::SecureLinkOnly,
                'provider_name' => 'ZEPTOMAIL',
                'dispatch_state' => EmailDeliveryState::Accepted,
                'accepted_at' => now(),
            ]);
            EmailDeliveryRecipient::query()->create([
                'delivery_id' => $delivery->id,
                'role' => 'TO',
                'email' => 'invoice@example.com',
                'display_order' => 1,
            ]);
        });

        $this->actingAs($owner)
            ->get(route('quotes.edit', [$company, $quote]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoiceAllocation.invoices.0.canUnlink', false));
        $this->post(route('quotes.invoices.unlink', [$company, $quote, $invoice]), [
            'reason' => 'Delivery history should be permanent',
            'confirmed' => true,
        ])->assertSessionHasErrors('unlink');

        $this->assertSame(1, $this->tenant(
            $company,
            fn (): int => QuoteInvoiceLink::query()->count(),
        ));
    }
}
