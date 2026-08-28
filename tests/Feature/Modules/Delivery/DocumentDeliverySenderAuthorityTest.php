<?php

namespace Tests\Feature\Modules\Delivery;

use App\Models\User;
use App\Modules\Companies\Actions\RemoveCompanyMember;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Contracts\SendsProviderEmail;
use App\Modules\Delivery\Data\EmailDeliveryState;
use App\Modules\Delivery\Data\ProviderDelivery;
use App\Modules\Delivery\Data\ProviderDeliveryResult;
use App\Modules\Delivery\Models\EmailDelivery;
use Illuminate\Support\Facades\Queue;
use Tests\Support\DocumentDeliveryTestCase;

final class DocumentDeliverySenderAuthorityTest extends DocumentDeliveryTestCase
{
    public function test_the_provider_boundary_rechecks_sender_suspension(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        Queue::fake();
        $this->actingAs($owner)->post(
            route('quotes.deliveries.store', [$company, $quote]),
            $this->deliveryPayload($quote),
        );
        $delivery = $this->tenant($company, fn (): EmailDelivery => EmailDelivery::query()->sole());
        $owner->forceFill(['suspended_at' => now()])->save();
        $provider = new SenderAuthorityProvider;

        $this->executeDeliveryJob($company->id, $delivery->id, $provider);

        $this->assertRejectedWithoutProviderCall($company, $provider);
    }

    public function test_the_provider_boundary_rechecks_company_membership(): void
    {
        [$owner, $company] = $this->company();
        $member = User::factory()->create();
        $membership = $company->memberships()->create([
            'user_id' => $member->id,
            'role' => CompanyRole::Member,
        ]);
        $quote = $this->completeQuote($company, $this->quote($company, $member));
        Queue::fake();
        $this->actingAs($member)->post(
            route('quotes.deliveries.store', [$company, $quote]),
            $this->deliveryPayload($quote),
        );
        $delivery = $this->tenant($company, fn (): EmailDelivery => EmailDelivery::query()->sole());
        $this->tenant(
            $company,
            fn () => app(RemoveCompanyMember::class)->handle($company, $owner, $membership),
        );
        $provider = new SenderAuthorityProvider;

        $this->executeDeliveryJob($company->id, $delivery->id, $provider);

        $this->assertRejectedWithoutProviderCall($company, $provider);
    }

    private function assertRejectedWithoutProviderCall(
        Company $company,
        SenderAuthorityProvider $provider,
    ): void {
        $this->assertSame(0, $provider->calls);
        $this->tenant($company, function (): void {
            $delivery = EmailDelivery::query()->sole();
            $this->assertSame(EmailDeliveryState::Rejected, $delivery->dispatch_state);
            $this->assertSame('sender_access_unavailable', $delivery->failure_category);
        });
    }
}

final class SenderAuthorityProvider implements SendsProviderEmail
{
    public int $calls = 0;

    public function send(ProviderDelivery $delivery): ProviderDeliveryResult
    {
        $this->calls++;

        return ProviderDeliveryResult::accepted('accepted');
    }
}
