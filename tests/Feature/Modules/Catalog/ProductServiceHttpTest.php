<?php

namespace Tests\Feature\Modules\Catalog;

use App\Foundation\Money\DecimalRules;
use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ProductServiceHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_owner_manages_the_complete_product_service_lifecycle(): void
    {
        $owner = User::factory()->create();
        $company = $this->companyFor($owner);
        [$currency, $tax] = $this->sources($company);
        $this->actingAs($owner);

        $this->get(route('catalog.index', $company))->assertInertia(fn (Assert $page) => $page
            ->component('catalog/index')
            ->has('products.items', 0)
            ->where('summary.active.count', 0)
            ->where('translations.index.title', 'Products')
            ->where('createUrl', route('catalog.create', $company, false)));

        $this->get(route('catalog.create', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalog/create')
                ->where('indexUrl', route('catalog.index', $company, false))
                ->where('storeUrl', route('catalog.store', $company, false))
                ->where('limits.description', 5000));

        $response = $this->post(
            route('catalog.store', $company),
            $this->payload($currency, $tax),
        );
        $product = $this->tenant($company, fn (): ProductService => ProductService::query()->sole());
        $response->assertRedirect(route('catalog.show', [$company, $product]))
            ->assertSessionHas('status');

        $this->assertSame('120.50', $this->displayPrice($product, $currency));
        $this->get(route('catalog.show', [$company, $product]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalog/show')
                ->where('product.name', 'Consulting')
                ->where('product.unitPrice', '120.50')
                ->where('product.archived', false)
                ->where('workspaceUrl', route('catalog.show', [$company, $product], false))
                ->where('updateUrl', route('catalog.update', [$company, $product], false))
                ->where('translations.actions.open', 'Open'));
        $this->patch(route('catalog.update', [$company, $product]), [
            ...$this->payload($currency, $tax),
            'name' => 'Consulting Plus',
            'unit_price' => '0',
        ])->assertRedirect()->assertSessionHas('status');

        $this->post(route('catalog.archive', [$company, $product]))
            ->assertRedirect()->assertSessionHas('status');
        $this->get(route('catalog.show', [$company, $product]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.archived', true)
                ->where('updateUrl', null)
                ->where('archiveUrl', null)
                ->where('restoreUrl', route('catalog.restore', [$company, $product], false)));
        $this->post(route('catalog.restore', [$company, $product]))
            ->assertRedirect()->assertSessionHas('status');
        $this->delete(route('catalog.destroy', [$company, $product]))
            ->assertRedirect(route('catalog.index', $company))
            ->assertSessionHas('status');

        $this->tenant($company, function (): void {
            $this->assertSame(0, ProductService::query()->count());
            $this->assertSame(5, AuditEvent::query()
                ->where('target_type', 'ProductService')->count());
            $encoded = AuditEvent::query()->where('target_type', 'ProductService')->get()->toJson();
            $this->assertStringNotContainsString('Consulting', $encoded);
            $this->assertStringNotContainsString('120.50', $encoded);
            $this->assertStringNotContainsString('Detailed customer-facing description', $encoded);
        });
    }

    public function test_catalog_search_sort_cursor_and_validation_are_safe(): void
    {
        $owner = User::factory()->create();
        $company = $this->companyFor($owner);
        $this->tenant($company, function (): void {
            foreach (range(1, 27) as $number) {
                ProductService::query()->create(['name' => sprintf('Entry %02d', $number)]);
            }
            ProductService::query()->create(['name' => 'Discount 50%', 'internal_code' => 'A_B']);
            ProductService::query()->create(['name' => 'Discount 500', 'internal_code' => 'ACB']);
        });
        $this->actingAs($owner);

        $first = $this->get(route('catalog.index', [
            'company' => $company, 'status' => 'all', 'sort' => 'name_asc',
        ]))->assertInertia(fn (Assert $page) => $page
            ->has('products.items', 25)
            ->where('products.nextUrl', fn (mixed $url): bool => is_string($url)));
        $this->get((string) $first->inertiaProps('products.nextUrl'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.items', 4));
        $this->get(route('catalog.index', ['company' => $company, 'q' => '50%']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.items', 1)->where('products.items.0.name', 'Discount 50%'));
        $this->get(route('catalog.index', ['company' => $company, 'q' => 'A_B']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.items', 1)->where('products.items.0.name', 'Discount 50%'));

        $this->get(route('catalog.index', [
            'company' => $company, 'q' => str_repeat('x', 121),
            'status' => 'deleted', 'sort' => 'random', 'per_page' => 1000,
        ]))->assertSessionHasErrors(['q', 'status', 'sort', 'per_page']);
    }

    public function test_admin_can_manage_while_member_and_cross_company_access_are_denied(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $otherOwner = User::factory()->create();
        $company = $this->companyFor($owner);
        $other = $this->companyFor($otherOwner);
        $company->memberships()->create(['user_id' => $admin->id, 'role' => CompanyRole::Admin]);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $otherProduct = $this->tenant($other, fn (): ProductService => ProductService::query()->create(['name' => 'Other']));

        $this->actingAs($admin)->post(route('catalog.store', $company), [
            'name' => 'Admin entry', 'period_unit' => 'NONE',
        ])->assertRedirect();
        $this->actingAs($member)->get(route('catalog.index', $company))->assertForbidden();
        $this->get(route('catalog.create', $company))->assertForbidden();
        $this->get(route('catalog.show', [$company, $otherProduct]))->assertForbidden();
        $this->post(route('catalog.store', $company), [
            'name' => 'Forbidden', 'period_unit' => 'NONE',
        ])->assertForbidden();
        $this->actingAs($owner)->patch(route('catalog.update', [$company, $otherProduct]), [
            'name' => 'Cross Company', 'period_unit' => 'NONE',
        ])->assertNotFound();
        $this->get(route('catalog.show', [$company, $otherProduct]))->assertNotFound();
        $this->get(route('catalog.index', $other))->assertNotFound();
    }

    public function test_validation_rejects_unbounded_values_and_unavailable_defaults(): void
    {
        $owner = User::factory()->create(['language_code' => 'ro']);
        $otherOwner = User::factory()->create();
        $company = $this->companyFor($owner);
        $other = $this->companyFor($otherOwner);
        [$currency, $tax] = $this->sources($company);
        [$inactive, $archived, $foreignCurrency] = [
            $this->tenant($company, fn (): CompanyCurrency => CompanyCurrency::query()->create([
                'currency_code' => 'EUR', 'currency_precision' => 2,
                'is_default' => false, 'active' => false,
            ])),
            $this->tenant($company, fn (): TaxPreset => TaxPreset::query()->create([
                'name' => 'Arhivată', 'percentage' => '5',
                'is_default' => false, 'archived_at' => now(),
            ])),
            $this->tenant($other, fn (): CompanyCurrency => CompanyCurrency::query()->create([
                'currency_code' => 'USD', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ])),
        ];
        $this->actingAs($owner);
        $url = route('catalog.store', $company);

        $this->post($url, [
            ...$this->payload($currency, $tax),
            'name' => str_repeat('x', 161),
            'description' => str_repeat('x', 5001),
            'internal_code' => str_repeat('x', 121),
            'unit' => str_repeat('x', 81),
        ])->assertSessionHasErrors(['name', 'description', 'internal_code', 'unit']);
        $this->post($url, [...$this->payload($currency, $tax), 'unit_price' => '1.234'])
            ->assertSessionHasErrors('unit_price');
        $this->post($url, [...$this->payload($inactive, $tax), 'unit_price' => '1.00'])
            ->assertSessionHasErrors('currency_id');
        $this->post($url, [...$this->payload($currency, $archived)])
            ->assertSessionHasErrors('tax_preset_id');
        $this->post($url, [...$this->payload($foreignCurrency, $tax)])
            ->assertSessionHasErrors('currency_id');
        $this->post($url, ['name' => 'Missing currency', 'unit_price' => '1', 'period_unit' => 'NONE'])
            ->assertSessionHasErrors('currency_id');
    }

    /** @return array<string, mixed> */
    private function payload(CompanyCurrency $currency, TaxPreset $tax): array
    {
        return [
            'name' => 'Consulting',
            'description' => 'Detailed customer-facing description',
            'internal_code' => 'CONSULT',
            'unit_price' => '120.50',
            'currency_id' => $currency->id,
            'unit' => 'hour',
            'period_unit' => 'MONTH',
            'tax_preset_id' => $tax->id,
        ];
    }

    /** @return array{CompanyCurrency, TaxPreset} */
    private function sources(Company $company): array
    {
        return $this->tenant($company, fn (): array => [
            CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]),
            TaxPreset::query()->create([
                'name' => 'TVA', 'percentage' => '19', 'is_default' => true,
            ]),
        ]);
    }

    private function displayPrice(ProductService $product, CompanyCurrency $currency): string
    {
        return (string) DecimalRules::moneySource(
            (string) $product->unit_price,
        )->toScale($currency->currency_precision);
    }

    private function companyFor(User $owner): Company
    {
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);

        return app(CreateCompany::class)->handle($account, $owner, 'Catalog Test SRL');
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
