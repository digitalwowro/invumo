<?php

namespace Tests\Feature\Modules\Delivery;

use App\Modules\Delivery\Actions\RevokePublicDocumentLink;
use App\Modules\Delivery\Contracts\SendsProviderEmail;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\ProviderDelivery;
use App\Modules\Delivery\Data\ProviderDeliveryResult;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Documents\Data\DocumentKind;
use Illuminate\Support\Facades\Queue;
use Tests\Support\DocumentDeliveryTestCase;

final class DocumentDeliveryEligibilityJobTest extends DocumentDeliveryTestCase
{
    public function test_revoked_link_rejects_delivery_before_provider_submission(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        Queue::fake();
        $this->actingAs($owner)->post(
            route('quotes.deliveries.store', [$company, $quote]),
            $this->deliveryPayload($quote),
        );
        $delivery = $this->tenant($company, fn (): EmailDelivery => EmailDelivery::query()->sole());
        app(RevokePublicDocumentLink::class)->handle(
            $company,
            $owner,
            $quote->id,
            DocumentKind::Quote,
        );
        $provider = new RejectsUnexpectedProviderCall;

        $this->executeDeliveryJob($company->id, $delivery->id, $provider);

        $this->assertSame(0, $provider->calls);
        $this->tenant($company, function (): void {
            $delivery = EmailDelivery::query()->sole();
            $this->assertSame(EmailDeliveryState::Rejected, $delivery->dispatch_state);
            $this->assertSame('public_link_unavailable', $delivery->failure_category);
        });
    }
}

final class RejectsUnexpectedProviderCall implements SendsProviderEmail
{
    public int $calls = 0;

    public function send(ProviderDelivery $delivery): ProviderDeliveryResult
    {
        $this->calls++;

        return ProviderDeliveryResult::accepted(null);
    }
}
