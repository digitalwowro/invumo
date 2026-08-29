<?php

namespace Tests\Feature\Modules\Invoices;

use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Queries\InvoiceDeletionPreview;
use Tests\Support\PublicDocumentTestCase;

final class InvoiceDeletionStateHttpTest extends PublicDocumentTestCase
{
    public function test_exposure_change_rejects_a_stale_low_risk_confirmation(): void
    {
        [$owner, $company] = $this->company();
        $invoice = $this->invoice($company, $owner);
        $state = $this->invoiceDeletionState($company, $invoice);
        $this->tenant($company, fn () => PublicDocumentLink::query()->create([
            'document_id' => $invoice->id,
            'generation' => 1,
            'token_hash' => hash('sha256', 'stale-invoice-link'),
            'token_ciphertext' => 'stale-invoice-link',
            'expires_at' => now()->addDay(),
            'created_by_user_id' => $owner->id,
        ]));

        $this->actingAs($owner)->delete(route('invoices.destroy', [$company, $invoice]), [
            'confirmed' => true,
            'confirmed_high_risk' => false,
            'deletion_state' => $state,
        ])->assertSessionHasErrors('invoice');

        $preview = $this->tenant($company, fn (): array => app(InvoiceDeletionPreview::class)
            ->for($invoice->id, Invoice::query()->findOrFail($invoice->id)->lifecycle));
        $this->assertTrue($preview['highRisk']);
        $this->assertNotSame($state, $preview['stateVersion']);
    }
}
