<?php

namespace Tests\Feature\Modules\Customers;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerContact;
use App\Modules\Customers\Models\CustomerDeliveryRecipient;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CustomerTenantIsolationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_customers_are_forced_rls_and_default_deny(): void
    {
        $companyA = $this->company('Alpha SRL');
        $companyB = $this->company('Beta SRL');
        $rls = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
            SELECT relrowsecurity, relforcerowsecurity
            FROM pg_class
            WHERE oid = 'public.customers'::regclass
            SQL);

        $this->assertTrue($rls->relrowsecurity);
        $this->assertTrue($rls->relforcerowsecurity);
        $this->assertSame(0, DB::connection('pgsql_schema')->table('customers')->count());

        $customerId = $this->tenant($companyA, function (): string {
            return Customer::query()->create([
                'type' => 'COMPANY', 'legal_name' => 'Alpha Customer SRL',
            ])->id;
        });
        $this->tenant($companyB, fn () => $this->assertNull(Customer::query()->find($customerId)));
    }

    public function test_runtime_cannot_insert_a_customer_for_another_company(): void
    {
        $companyA = $this->company('Alpha SRL');
        $companyB = $this->company('Beta SRL');
        $this->expectException(QueryException::class);

        $this->tenant($companyA, function () use ($companyB): void {
            DB::connection('pgsql')->table('customers')->insert([
                'id' => (string) Str::uuid7(),
                'company_id' => $companyB->id,
                'type' => 'COMPANY',
                'legal_name' => 'Cross Company Customer',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function test_contact_and_recipient_tables_are_forced_rls_and_cross_company_hidden(): void
    {
        $ownerA = User::factory()->create();
        $companyA = $this->companyFor($ownerA, 'Alpha SRL');
        $companyB = $this->company('Beta SRL');
        $customerA = $this->createCustomer($companyA, ['legal_name' => 'Alpha Customer']);
        $customerB = $this->createCustomer($companyB, ['legal_name' => 'Beta Customer']);
        $contactA = $this->createContact($companyA, $customerA, 'Alpha Contact', 'alpha@example.com');
        $contactB = $this->createContact($companyB, $customerB, 'Beta Contact', 'beta@example.com');

        foreach (['customer_contacts', 'customer_delivery_recipients'] as $table) {
            $rls = DB::connection('pgsql_schema')->selectOne(<<<SQL
                SELECT relrowsecurity, relforcerowsecurity
                FROM pg_class
                WHERE oid = 'public.{$table}'::regclass
                SQL);
            $this->assertTrue($rls->relrowsecurity);
            $this->assertTrue($rls->relforcerowsecurity);
        }

        $this->tenant($companyB, function () use ($contactA): void {
            $this->assertNull(CustomerContact::query()->find($contactA->id));
            $this->assertSame(0, CustomerDeliveryRecipient::query()->count());
        });

        $this->actingAs($ownerA)
            ->patch(
                route('customer-contacts.update', [$companyA, $customerA, $contactB]),
                ['name' => 'Missing', 'is_primary' => false, 'is_billing' => false],
            )->assertNotFound();
        $this->actingAs(User::factory()->create())
            ->get(route('customer-contacts.index', [$companyB, $customerB]))
            ->assertNotFound();
    }

    public function test_same_company_foreign_keys_reject_cross_company_contact_ownership(): void
    {
        $companyA = $this->company('Alpha SRL');
        $companyB = $this->company('Beta SRL');
        $customerB = $this->createCustomer($companyB, ['legal_name' => 'Beta Customer']);
        $this->expectException(QueryException::class);

        $this->tenant($companyA, fn () => CustomerContact::query()->create([
            'customer_id' => $customerB->id,
            'name' => 'Cross Company Contact',
            'display_order' => 0,
        ]));
    }

    public function test_database_rejects_invalid_or_duplicate_delivery_recipients(): void
    {
        $company = $this->company('Alpha SRL');
        $customer = $this->createCustomer($company, ['legal_name' => 'Alpha Customer']);
        $this->expectException(PDOException::class);

        $this->tenant($company, function () use ($customer): void {
            foreach (['TO', 'CC'] as $role) {
                CustomerDeliveryRecipient::query()->create([
                    'customer_id' => $customer->id,
                    'role' => $role,
                    'explicit_email' => 'same@example.com',
                    'display_order' => 0,
                ]);
            }
        });
    }

    public function test_database_rejects_multiple_active_primary_contacts(): void
    {
        $company = $this->company('Alpha SRL');
        $customer = $this->createCustomer($company, ['legal_name' => 'Alpha Customer']);
        $this->createContact($company, $customer, 'First Primary', 'first@example.com', true);
        $this->expectException(QueryException::class);

        $this->createContact($company, $customer, 'Second Primary', 'second@example.com', true);
    }

    public function test_database_rejects_an_invalid_contact_envelope(): void
    {
        $company = $this->company('Alpha SRL');
        $customer = $this->createCustomer($company, ['legal_name' => 'Alpha Customer']);
        $this->expectException(QueryException::class);

        $this->createContact($company, $customer, str_repeat('x', 161), 'valid@example.com');
    }

    public function test_database_rejects_a_contact_recipient_without_an_active_email(): void
    {
        $company = $this->company('Alpha SRL');
        $customer = $this->createCustomer($company, ['legal_name' => 'Alpha Customer']);
        $contact = $this->createContact($company, $customer, 'No Email', null);
        $this->expectException(PDOException::class);

        $this->tenant($company, fn () => CustomerDeliveryRecipient::query()->create([
            'customer_id' => $customer->id,
            'role' => 'TO',
            'contact_id' => $contact->id,
            'display_order' => 0,
        ]));
    }

    #[DataProvider('invalidCustomers')]
    public function test_database_rejects_invalid_customer_envelopes(array $attributes): void
    {
        $company = $this->company('Alpha SRL');
        $this->expectException(QueryException::class);

        $this->tenant($company, fn () => DB::connection('pgsql')->table('customers')->insert([
            'id' => (string) Str::uuid7(),
            'company_id' => $company->id,
            'type' => 'COMPANY',
            'legal_name' => 'Customer SRL',
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]));
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidCustomers(): array
    {
        return [
            'company without legal name' => [[
                'legal_name' => null,
            ]],
            'company with individual name' => [[
                'first_name' => 'Ada',
            ]],
            'individual without last name' => [[
                'type' => 'INDIVIDUAL', 'legal_name' => null, 'first_name' => 'Ada',
            ]],
            'unpaired tax registration' => [[
                'tax_registration_label' => 'CUI',
            ]],
            'unsupported country shape' => [[
                'country_code' => 'rou',
            ]],
            'oversized internal notes' => [[
                'internal_notes' => str_repeat('x', 5001),
            ]],
        ];
    }

    private function company(string $name): Company
    {
        $owner = User::factory()->create();
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $account = Account::query()->create(['owner_user_id' => $owner->id, 'plan_id' => $plan->id]);

        return app(CreateCompany::class)->handle($account, $owner, $name);
    }

    private function companyFor(User $owner, string $name): Company
    {
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $account = Account::query()->create(['owner_user_id' => $owner->id, 'plan_id' => $plan->id]);

        return app(CreateCompany::class)->handle($account, $owner, $name);
    }

    private function createCustomer(Company $company, array $attributes): Customer
    {
        return $this->tenant($company, fn (): Customer => Customer::query()->create([
            'type' => 'COMPANY', ...$attributes,
        ]));
    }

    private function createContact(
        Company $company,
        Customer $customer,
        string $name,
        ?string $email,
        bool $primary = false,
    ): CustomerContact {
        return $this->tenant($company, fn (): CustomerContact => CustomerContact::query()->create([
            'customer_id' => $customer->id,
            'name' => $name,
            'email' => $email,
            'is_primary' => $primary,
            'display_order' => 0,
        ]));
    }

    private function tenant(Company $company, callable $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
