<?php

namespace Tests\Feature\Modules\Delivery;

use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Delivery\Actions\CreatePublicDocumentLink;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use Tests\Support\PublicDocumentTestCase;

final class PublicDocumentDeletionTest extends PublicDocumentTestCase
{
    public function test_quote_deletion_requires_high_risk_confirmation_and_erases_link_credentials(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->quote($company, $owner);
        app(CreatePublicDocumentLink::class)->handle(
            $company,
            $owner,
            $quote->id,
            DocumentKind::Quote,
        );
        $token = $this->currentToken($company, $quote->id);
        $this->actingAs($owner);

        $this->delete(route('quotes.destroy', [$company, $quote]), [
            'confirmed' => true,
            'confirmed_high_risk' => false,
            'deletion_state' => $this->quoteDeletionState($company, $quote),
        ])->assertSessionHasErrors('quote');
        $this->delete(route('quotes.destroy', [$company, $quote]), [
            'confirmed' => true,
            'confirmed_high_risk' => true,
            'deletion_state' => $this->quoteDeletionState($company, $quote),
        ])->assertRedirect(route('quotes.index', $company));

        $this->get(route('public-quotes.show', $token))->assertNotFound();
        $this->tenant($company, function () use ($quote): void {
            $this->assertNull(Document::query()->find($quote->id));
            $this->assertSame(0, PublicDocumentLink::query()->count());
            $this->assertSame(true, AuditEvent::query()
                ->where('action', 'company.quote.deleted')
                ->sole()->before['had_public_link_history']);
        });
    }

    public function test_invoice_deletion_erases_link_credentials_and_retains_exposure_fact(): void
    {
        [$owner, $company] = $this->company();
        $invoice = $this->invoice($company, $owner);
        app(CreatePublicDocumentLink::class)->handle(
            $company,
            $owner,
            $invoice->id,
            DocumentKind::Invoice,
        );
        $token = $this->currentToken($company, $invoice->id);

        $this->actingAs($owner)->delete(route('invoices.destroy', [$company, $invoice]), [
            'confirmed' => true,
            'confirmed_high_risk' => true,
            'confirmation_number' => $invoice->rendered_number,
            'deletion_state' => $this->invoiceDeletionState($company, $invoice),
        ])->assertRedirect(route('invoices.index', $company));

        $this->get(route('public-invoices.show', $token))->assertNotFound();
        $this->tenant($company, function () use ($invoice): void {
            $this->assertNull(Document::query()->find($invoice->id));
            $this->assertSame(0, PublicDocumentLink::query()->count());
            $this->assertSame(true, AuditEvent::query()
                ->where('action', 'company.invoice.deleted')
                ->sole()->before['had_public_link_history']);
        });
    }
}
