<?php

namespace Tests\Feature\Modules\Customers;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CustomerListHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_customer_list_searches_filters_sorts_and_paginates(): void
    {
        $owner = User::factory()->create();
        $company = $this->companyFor($owner);
        $this->seedCustomers($company);
        $this->actingAs($owner);

        $this->get(route('customers.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->component('customers/index')
                ->has('customers.items', 25)
                ->where('customers.items.0.displayName', 'Newest Customer SRL')
                ->where('filters.status', 'active')
                ->where('customers.previousUrl', null)
                ->where('customers.nextUrl', fn (mixed $value): bool => is_string($value))
                ->where('abilities.create', true)
                ->where('limits.internalNotes', 5000));

        $this->get(route('customers.index', [
            'company' => $company,
            'q' => 'needle@example.com',
            'status' => 'all',
        ]))->assertInertia(fn (Assert $page) => $page
            ->has('customers.items', 1)
            ->where('customers.items.0.displayName', 'Search Needle SRL')
            ->where('filters.q', 'needle@example.com'));

        $this->get(route('customers.index', [
            'company' => $company,
            'status' => 'archived',
            'country' => 'RO',
            'sort' => 'name_asc',
        ]))->assertInertia(fn (Assert $page) => $page
            ->has('customers.items', 1)
            ->where('customers.items.0.displayName', 'Archived Romanian SRL')
            ->where('customers.items.0.archived', true));

        $nameAscending = $this->get(route('customers.index', [
            'company' => $company,
            'status' => 'all',
            'sort' => 'name_asc',
        ]));
        $nameAscending->assertInertia(fn (Assert $page) => $page
            ->has('customers.items', 25)
            ->where('customers.nextUrl', fn (mixed $value): bool => is_string($value)));

        $this->get((string) $nameAscending->inertiaProps('customers.nextUrl'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('customers.items', 3)
                ->where('customers.items.0.displayName', 'Customer 25 SRL')
                ->where('customers.previousUrl', fn (mixed $value): bool => is_string($value)));

        $nameDescending = $this->get(route('customers.index', [
            'company' => $company,
            'status' => 'all',
            'sort' => 'name_desc',
        ]));

        $this->get((string) $nameDescending->inertiaProps('customers.nextUrl'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('customers.items', 3)
                ->where('customers.items.0.displayName', 'Customer 02 SRL'));
    }

    public function test_search_treats_like_metacharacters_as_literals(): void
    {
        $owner = User::factory()->create();
        $company = $this->companyFor($owner);

        foreach (['Discount 50% SRL', 'Discount 500 SRL', 'Code A_B SRL', 'Code ACB SRL'] as $name) {
            $this->create($company, ['legal_name' => $name]);
        }

        $this->actingAs($owner);

        $this->get(route('customers.index', ['company' => $company, 'q' => '50%']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('customers.items', 1)
                ->where('customers.items.0.displayName', 'Discount 50% SRL'));

        $this->get(route('customers.index', ['company' => $company, 'q' => 'A_B']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('customers.items', 1)
                ->where('customers.items.0.displayName', 'Code A_B SRL'));
    }

    public function test_cross_company_customer_routes_are_not_visible(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $companyA = $this->companyFor($ownerA, 'Alpha SRL');
        $companyB = $this->companyFor($ownerB, 'Beta SRL');
        $customerB = $this->create($companyB, ['legal_name' => 'Beta Customer SRL']);

        $this->actingAs($ownerA)
            ->get(route('customers.show', [$companyA, $customerB->id]))
            ->assertNotFound();
        $this->patch(route('customers.update', [$companyA, $customerB->id]), [
            'type' => 'COMPANY', 'legal_name' => 'Cross Company Edit',
        ])->assertNotFound();
        $this->get(route('customers.index', $companyB))->assertNotFound();
    }

    public function test_list_filter_validation_rejects_unbounded_or_unknown_values(): void
    {
        $owner = User::factory()->create();
        $company = $this->companyFor($owner);

        $this->actingAs($owner)
            ->get(route('customers.index', [
                'company' => $company,
                'q' => str_repeat('x', 121),
                'status' => 'deleted',
                'sort' => 'random',
                'per_page' => 1000,
            ]))
            ->assertSessionHasErrors(['q', 'status', 'sort', 'per_page']);
    }

    private function seedCustomers(Company $company): void
    {
        $this->tenant($company, function (): void {
            foreach (range(1, 25) as $number) {
                Customer::query()->create([
                    'type' => 'COMPANY',
                    'legal_name' => sprintf('Customer %02d SRL', $number),
                    'country_code' => $number % 2 === 0 ? 'RO' : 'DE',
                ]);
            }
            Customer::query()->create([
                'type' => 'COMPANY', 'legal_name' => 'Search Needle SRL',
                'email' => 'needle@example.com', 'external_reference' => 'REF-NEEDLE',
            ]);
            Customer::query()->create([
                'type' => 'COMPANY', 'legal_name' => 'Archived Romanian SRL',
                'country_code' => 'RO', 'archived_at' => now(),
            ]);
            Customer::query()->create([
                'type' => 'COMPANY', 'legal_name' => 'Newest Customer SRL',
            ])->touch();
        });
    }

    private function companyFor(User $owner, string $name = 'Acme SRL'): Company
    {
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $account = Account::query()->create(['owner_user_id' => $owner->id, 'plan_id' => $plan->id]);

        return app(CreateCompany::class)->handle($account, $owner, $name);
    }

    private function tenant(Company $company, callable $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }

    private function create(Company $company, array $attributes): Customer
    {
        return $this->tenant($company, fn (): Customer => Customer::query()->create([
            'type' => 'COMPANY', 'legal_name' => 'Customer SRL', ...$attributes,
        ]));
    }
}
