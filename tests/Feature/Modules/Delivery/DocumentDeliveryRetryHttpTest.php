<?php

namespace Tests\Feature\Modules\Delivery;

use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Documents\Models\Document;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\DocumentDeliveryTestCase;

final class DocumentDeliveryRetryHttpTest extends DocumentDeliveryTestCase
{
    public function test_retry_is_unavailable_after_the_document_changes(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        Queue::fake();
        $this->actingAs($owner)->post(
            route('quotes.deliveries.store', [$company, $quote]),
            $this->deliveryPayload($quote),
        );
        $delivery = $this->tenant($company, function () use ($quote): EmailDelivery {
            $delivery = EmailDelivery::query()->sole();
            $delivery->update([
                'dispatch_state' => EmailDeliveryState::Rejected,
                'failure_category' => 'provider_permanent_rejection',
                'failure_summary' => 'Rejected.',
                'failed_at' => now(),
            ]);
            Document::query()->whereKey($quote->id)->update([
                'edit_version' => $quote->edit_version + 1,
                'content_version' => $quote->content_version + 1,
            ]);

            return $delivery;
        });

        $this->get(route('quotes.edit', [$company, $quote]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('directDelivery.history.0.retryUrl', null));
        $this->post(
            route('quotes.deliveries.retry', [$company, $quote, $delivery]),
            ['confirmed' => true],
        )->assertSessionHasErrors('delivery');
    }
}
