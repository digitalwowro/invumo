<?php

namespace Tests\Feature\Modules\Customers;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CustomerLifecycleHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_owner_completes_the_customer_lifecycle_without_sensitive_audit_values(): void
    {
        $owner = $this->user('owner@example.com');
        $company = $this->companyFor($owner);
        $this->actingAs($owner);

        $response = $this->post(route('customers.store', $company), $this->individual());
        $response->assertRedirect()->assertSessionHas('status');
        $customer = $this->customer($company);
        $this->assertStringContainsString($customer->id, $response->headers->get('Location'));
        $this->assertSame('Ada', $customer->first_name);
        $this->assertSame('ada@example.com', $customer->email);

        $this->get(route('customers.show', [$company, $customer]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('customers/show')
                ->where('customer.displayName', 'Ada Lovelace')
                ->where('customer.internalNotes', 'Private planning note')
                ->where('abilities.update', true)
                ->where('abilities.delete', true)
                ->where('translations.form.fields.first_name', 'First name'));

        $this->patch(route('customers.update', [$company, $customer]), $this->companyCustomer())
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->patch(route('customers.update', [$company, $customer]), $this->companyCustomer())
            ->assertRedirect();

        $this->tenant($company, function () use ($customer): void {
            $stored = Customer::query()->findOrFail($customer->id);
            $this->assertSame('COMPANY', $stored->type->value);
            $this->assertSame('Analytical Engines SRL', $stored->legal_name);
            $this->assertNull($stored->first_name);
            $this->assertNull($stored->last_name);

            $events = AuditEvent::query()
                ->where('target_id', $customer->id)
                ->orderBy('occurred_at')
                ->get();
            $this->assertCount(2, $events);
            $this->assertSame(['changed_fields', 'type'], $this->sortedKeys($events[0]->after));
            $this->assertSame(['changed_fields', 'type'], $this->sortedKeys($events[1]->before));
            $this->assertSame(['changed_fields', 'type'], $this->sortedKeys($events[1]->after));
            $encoded = $events->toJson();
            $this->assertStringNotContainsString('Ada', $encoded);
            $this->assertStringNotContainsString('ada@example.com', $encoded);
            $this->assertStringNotContainsString('Private planning note', $encoded);
            $this->assertStringNotContainsString('Strada Secretă', $encoded);
            $this->assertStringNotContainsString('RO123456', $encoded);
        });

        $this->post(route('customers.archive', [$company, $customer]))
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->patch(route('customers.update', [$company, $customer]), $this->companyCustomer())
            ->assertSessionHasErrors('customer');
        $this->get(route('customers.show', [$company, $customer]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('customer.archived', true)
                ->where('updateUrl', null)
                ->where('restoreUrl', fn (mixed $value): bool => is_string($value)));
        $this->post(route('customers.restore', [$company, $customer]))
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->delete(route('customers.destroy', [$company, $customer]))
            ->assertRedirect(route('customers.index', $company))
            ->assertSessionHas('status');

        $this->tenant($company, function () use ($customer): void {
            $this->assertNull(Customer::query()->find($customer->id));
            $deleted = AuditEvent::query()
                ->where('target_id', $customer->id)
                ->where('action', 'company.customer.deleted')
                ->firstOrFail();
            $this->assertSame(['deleted' => false], $deleted->before);
            $this->assertSame(['deleted' => true], $deleted->after);
        });
    }

    public function test_member_manages_customers_but_cannot_delete_them(): void
    {
        $owner = $this->user('owner@example.com');
        $member = $this->user('member@example.com');
        $company = $this->companyFor($owner);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);

        $this->actingAs($member)
            ->get(route('customers.index', $company))
            ->assertOk();
        $this->post(route('customers.store', $company), $this->individual())
            ->assertRedirect();
        $customer = $this->customer($company);
        $this->post(route('customers.archive', [$company, $customer]))
            ->assertRedirect();
        $this->delete(route('customers.destroy', [$company, $customer]))
            ->assertForbidden();
    }

    public function test_admin_can_permanently_delete_a_customer(): void
    {
        $owner = $this->user('owner@example.com');
        $admin = $this->user('admin@example.com');
        $company = $this->companyFor($owner);
        $company->memberships()->create(['user_id' => $admin->id, 'role' => CompanyRole::Admin]);
        $customer = $this->createCustomer($company, ['legal_name' => 'Delete Me SRL']);

        $this->actingAs($admin)
            ->delete(route('customers.destroy', [$company, $customer]))
            ->assertRedirect(route('customers.index', $company));
    }

    public function test_customer_validation_is_localized_and_bounded(): void
    {
        $owner = $this->user('owner@example.com');
        $owner->update(['language_code' => 'ro']);
        $company = $this->companyFor($owner);
        $this->actingAs($owner);

        $this->post(route('customers.store', $company), [
            'type' => 'INDIVIDUAL',
            'first_name' => '',
            'last_name' => '',
            'email' => 'invalid',
            'internal_notes' => str_repeat('ă', 5001),
            'tax_registration_label' => 'CUI',
        ])->assertSessionHasErrors([
            'first_name', 'last_name', 'email', 'internal_notes',
            'tax_registration_identifier',
        ]);

        $this->get(route('customers.create', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('translations.create.title', 'Client nou')
                ->where('limits.internalNotes', 5000));
    }

    private function individual(): array
    {
        return [
            'type' => 'INDIVIDUAL', 'first_name' => 'Ada', 'last_name' => 'Lovelace',
            'email' => 'ADA@EXAMPLE.COM', 'phone' => '+40 700 000 000',
            'external_reference' => 'CUS-ADA', 'address_line_1' => 'Strada Secretă 1',
            'city' => 'București', 'postal_code' => '010101', 'country_code' => 'ro',
            'tax_registration_label' => 'CUI', 'tax_registration_identifier' => 'RO123456',
            'internal_notes' => 'Private planning note',
        ];
    }

    private function companyCustomer(): array
    {
        return [
            'type' => 'COMPANY', 'legal_name' => 'Analytical Engines SRL',
            'email' => 'office@example.com', 'phone' => '+40 21 000 0000',
            'external_reference' => 'CUS-AE', 'address_line_1' => 'Strada Secretă 2',
            'city' => 'București', 'region' => 'București', 'postal_code' => '020202',
            'country_code' => 'RO', 'business_registration_label' => 'J',
            'business_registration_number' => 'J40/1/2026', 'internal_notes' => 'New private note',
        ];
    }

    private function user(string $email): User
    {
        return User::factory()->create(['email' => $email]);
    }

    private function companyFor(User $owner): Company
    {
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $account = Account::query()->create(['owner_user_id' => $owner->id, 'plan_id' => $plan->id]);

        return app(CreateCompany::class)->handle($account, $owner, 'Acme SRL');
    }

    private function tenant(Company $company, callable $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }

    private function customer(Company $company): Customer
    {
        return $this->tenant($company, fn (): Customer => Customer::query()->firstOrFail());
    }

    private function createCustomer(Company $company, array $attributes): Customer
    {
        return $this->tenant($company, fn (): Customer => Customer::query()->create([
            'type' => 'COMPANY', 'legal_name' => 'Customer SRL', ...$attributes,
        ]));
    }

    /** @param array<string, mixed> $values */
    private function sortedKeys(array $values): array
    {
        $keys = array_keys($values);
        sort($keys);

        return $keys;
    }
}
