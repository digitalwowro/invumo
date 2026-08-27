<?php

namespace Tests\Feature\Modules\Quotes;

use App\Modules\Delivery\Actions\CreatePublicDocumentLink;
use App\Modules\Delivery\Actions\RevokePublicDocumentLink;
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
                ->where('invoiceAllocation.invoices.0.canUnlink', false));
        $this->post(route('quotes.invoices.unlink', [$company, $quote, $invoice]), [
            'reason' => 'Revoked link should not matter',
            'confirmed' => true,
        ])->assertSessionHasErrors('unlink');

        $this->tenant($company, fn () => $this->assertSame(
            1,
            QuoteInvoiceLink::query()->count(),
        ));
    }
}
