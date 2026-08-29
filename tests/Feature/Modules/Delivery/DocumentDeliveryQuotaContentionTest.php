<?php

namespace Tests\Feature\Modules\Delivery;

use App\Modules\Delivery\Contracts\SendsProviderEmail;
use App\Modules\Delivery\Data\ProviderDelivery;
use App\Modules\Delivery\Data\ProviderDeliveryResult;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Support\DocumentDeliveryQuota;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Queue;
use Tests\Support\DocumentDeliveryTestCase;

final class DocumentDeliveryQuotaContentionTest extends DocumentDeliveryTestCase
{
    public function test_contention_leaves_the_delivery_queued_for_retry(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        Queue::fake();
        $this->actingAs($owner)->post(
            route('quotes.deliveries.store', [$company, $quote]),
            $this->deliveryPayload($quote),
        );
        $delivery = $this->tenant($company, fn (): EmailDelivery => EmailDelivery::query()->sole());
        $store = new ArrayStore;
        $lock = $store->lock('document-delivery:quota-reservation', 70);
        $this->assertTrue($lock->acquire());
        app()->instance(DocumentDeliveryQuota::class, new DocumentDeliveryQuota(
            new Repository($store),
            0,
        ));
        $provider = new ContendedQuotaProvider;

        try {
            $this->expectException(LockTimeoutException::class);
            $this->executeDeliveryJob($company->id, $delivery->id, $provider);
        } finally {
            $lock->release();
            $this->assertSame(0, $provider->calls);
            $this->tenant($company, function (): void {
                $delivery = EmailDelivery::query()->sole();
                $this->assertSame('QUEUED', $delivery->dispatch_state->value);
                $this->assertNull($delivery->failure_category);
            });
        }
    }
}

final class ContendedQuotaProvider implements SendsProviderEmail
{
    public int $calls = 0;

    public function send(ProviderDelivery $delivery): ProviderDeliveryResult
    {
        $this->calls++;

        return ProviderDeliveryResult::accepted('unexpected');
    }
}
