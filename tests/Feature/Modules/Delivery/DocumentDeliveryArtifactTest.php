<?php

namespace Tests\Feature\Modules\Delivery;

use App\Foundation\Jobs\TenantJobExecution;
use App\Modules\Delivery\Actions\PrepareDocumentDeliveryArtifact;
use App\Modules\Delivery\Contracts\RendersDocumentPdf;
use App\Modules\Delivery\Data\DocumentArtifactLimits;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Models\DocumentArtifact;
use App\Modules\Delivery\Models\EmailDelivery;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DocumentDeliveryTestCase;

final class DocumentDeliveryArtifactTest extends DocumentDeliveryTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('document_artifacts_local');
        config()->set('invumo.document_artifacts.disk', 'document_artifacts_local');
    }

    public function test_oversized_pdf_is_rejected_before_provider_submission(): void
    {
        [$owner, $company] = $this->company();
        $invoice = $this->completeInvoice($company, $this->invoice($company, $owner));
        Queue::fake();
        $payload = $this->deliveryPayload($invoice);
        $payload['attachment_mode'] = 'ATTACH_PDF';
        $this->actingAs($owner)->post(
            route('invoices.deliveries.store', [$company, $invoice]),
            $payload,
        )->assertRedirect();
        $delivery = $this->tenant($company, fn (): EmailDelivery => EmailDelivery::query()->sole());
        $this->app->instance(
            RendersDocumentPdf::class,
            new OversizedPdfRenderer(DocumentArtifactLimits::MAX_BYTES + 1),
        );

        $handled = app(PrepareDocumentDeliveryArtifact::class)->handle(
            $company->id,
            $delivery->id,
            $delivery->id,
            app(TenantJobExecution::class),
        );

        $this->assertFalse($handled);
        $this->tenant($company, function (): void {
            $delivery = EmailDelivery::query()->sole();
            $this->assertSame(EmailDeliveryState::Rejected, $delivery->dispatch_state);
            $this->assertSame('artifact_too_large', $delivery->failure_category);
            $this->assertSame(0, DocumentArtifact::query()->count());
        });
    }
}

final readonly class OversizedPdfRenderer implements RendersDocumentPdf
{
    public function __construct(private int $bytes) {}

    public function render(string $html): string
    {
        return str_repeat('x', $this->bytes);
    }
}
