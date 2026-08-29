<?php

namespace Tests\Feature\Modules\Companies;

use App\Modules\Delivery\Data\EmailDeliveryAttemptState;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryAttempt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\DocumentDeliveryTestCase;

final class CompanyErasureDeliveryHttpTest extends DocumentDeliveryTestCase
{
    public function test_company_erasure_waits_while_provider_submission_is_in_flight(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        Queue::fake();
        $this->actingAs($owner)->post(
            route('quotes.deliveries.store', [$company, $quote]),
            $this->deliveryPayload($quote),
        );
        $this->tenant($company, function (): void {
            $delivery = EmailDelivery::query()->sole();
            EmailDeliveryAttempt::query()->create([
                'delivery_id' => $delivery->id,
                'attempt_number' => 1,
                'client_reference' => (string) Str::uuid7(),
                'state' => EmailDeliveryAttemptState::Pending,
                'submitted_at' => now(),
            ]);
        });
        $response = $this->get(route('company-data-lifecycle.show', $company));
        $state = $response->inertiaProps('erasure.stateVersion');
        $response->assertInertia(fn (Assert $page) => $page
            ->where('erasure.guard.blocked', true)
            ->where(
                'erasure.guard.description',
                '1 provider submission(s) still have an unknown outcome. Resolve them before erasing this Company.',
            ));

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('company-data-lifecycle.destroy', $company), [
                'confirmed' => true,
                'confirmed_high_risk' => true,
                'confirmation_name' => $company->name,
                'deletion_state' => $state,
            ])->assertSessionHasErrors('company');

        $this->assertNotNull($company->fresh());
    }
}
