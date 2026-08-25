<?php

namespace Tests\Feature\Modules\Customers;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerContact;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

final class CustomerContactIntegrityHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_referenced_contact_email_changes_return_field_validation_and_remain_atomic(): void
    {
        $owner = User::factory()->create();
        $company = $this->companyFor($owner);
        $customer = $this->tenant($company, fn (): Customer => Customer::query()->create([
            'type' => 'COMPANY', 'legal_name' => 'Client SRL',
        ]));
        $this->actingAs($owner);

        $this->post(
            route('customer-contacts.store', [$company, $customer]),
            $this->contact('First Contact', 'first@example.com'),
        )->assertRedirect();
        $this->post(
            route('customer-contacts.store', [$company, $customer]),
            $this->contact('Second Contact', 'second@example.com'),
        )->assertRedirect();
        $first = $this->contactNamed($company, 'First Contact');
        $second = $this->contactNamed($company, 'Second Contact');

        $this->patch(route('customer-delivery.update', [$company, $customer]), [
            'email_attachment_mode' => null,
            'recipients' => [
                ['role' => 'TO', 'contact_id' => $first->id],
                ['role' => 'CC', 'contact_id' => $second->id],
            ],
        ])->assertRedirect();

        $this->patch(
            route('customer-contacts.update', [$company, $customer, $first]),
            $this->contact('First Contact', null),
        )->assertSessionHasErrors([
            'email' => __('customers_ui.errors.contact_recipient_dependency'),
        ]);
        $this->patch(
            route('customer-contacts.update', [$company, $customer, $first]),
            $this->contact('First Contact', 'SECOND@EXAMPLE.COM'),
        )->assertSessionHasErrors([
            'email' => __('customers_ui.errors.contact_duplicate_recipient'),
        ]);

        $this->tenant($company, function () use ($first): void {
            $this->assertSame(
                'first@example.com',
                CustomerContact::query()->findOrFail($first->id)->email,
            );
            $this->assertSame(
                0,
                AuditEvent::query()
                    ->where('action', 'company.customer_contact.updated')
                    ->count(),
            );
        });
    }

    /** @return array<string, mixed> */
    private function contact(string $name, ?string $email): array
    {
        return [
            'name' => $name,
            'email' => $email,
            'phone' => '',
            'position_title' => '',
            'is_primary' => false,
            'is_billing' => false,
        ];
    }

    private function companyFor(User $owner): Company
    {
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);

        return app(CreateCompany::class)->handle($account, $owner, 'Contact Integrity SRL');
    }

    private function contactNamed(Company $company, string $name): CustomerContact
    {
        return $this->tenant(
            $company,
            fn (): CustomerContact => CustomerContact::query()->where('name', $name)->firstOrFail(),
        );
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
