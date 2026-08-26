<?php

namespace Tests\Feature\Modules\Quotes;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Quotes\Actions\CreateQuoteDraft;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class QuoteSourceAuthorizationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_member_can_use_sources_and_create_customer_but_cannot_create_catalog_entry(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $company = $this->company($owner);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        [$customer, $product] = $this->tenant($company, function (): array {
            $customer = Customer::query()->create([
                'type' => 'COMPANY', 'legal_name' => 'Selectable Customer',
            ]);
            $product = ProductService::query()->create([
                'name' => 'Selectable Product', 'period_unit' => 'NONE',
            ]);

            return [$customer, $product];
        });
        $quote = app(CreateQuoteDraft::class)->handle($company, $member, (string) Str::uuid7());
        $this->actingAs($member);

        $this->getJson(route('quote-sources.customers.index', [$company, 'q' => 'Selectable']))
            ->assertOk()->assertJsonPath('items.0.id', $customer->id);
        $this->getJson(route('quote-sources.products.index', [$company, 'q' => 'Selectable']))
            ->assertOk()->assertJsonPath('items.0.id', $product->id);
        $this->get(route('quotes.edit', [$company, $quote]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sourceAbilities.createCustomer', true)
                ->where('sourceAbilities.createProduct', false));

        $this->post(route('quotes.inline-customers.store', [$company, $quote]), [
            'type' => 'INDIVIDUAL', 'first_name' => 'Inline', 'last_name' => 'Customer',
        ])->assertRedirect()->assertSessionHas('inline_customer_id');
        $this->post(route('quotes.inline-products.store', [$company, $quote]), [
            'name' => 'Forbidden Product', 'period_unit' => 'NONE',
        ])->assertForbidden();

        $this->tenant($company, function (): void {
            $this->assertSame(2, Customer::query()->count());
            $this->assertSame(1, ProductService::query()->count());
        });
    }

    public function test_application_authorization_and_rls_hide_cross_company_sources(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $company = $this->company($owner);
        $other = $this->company($otherOwner);
        $otherCustomer = $this->tenant($other, fn (): Customer => Customer::query()->create([
            'type' => 'COMPANY', 'legal_name' => 'Other Customer',
        ]));
        $otherProduct = $this->tenant($other, fn (): ProductService => ProductService::query()->create([
            'name' => 'Other Product', 'period_unit' => 'NONE',
        ]));
        $this->actingAs($owner);

        $this->getJson(route('quote-sources.customers.show', [$company, $otherCustomer]))->assertNotFound();
        $this->getJson(route('quote-sources.products.show', [
            $company, $otherProduct, 'currency_code' => 'RON',
        ]))->assertNotFound();
        $this->get(route('quote-sources.customers.index', $other))->assertNotFound();

        $this->tenant($company, function () use ($otherCustomer, $otherProduct): void {
            $this->assertNull(Customer::query()->find($otherCustomer->id));
            $this->assertNull(ProductService::query()->find($otherProduct->id));
        });
    }

    private function company(User $owner): Company
    {
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Authorization SRL');
        $this->tenant($company, function (): void {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'Europe/Bucharest', 'default_document_language' => 'en',
            ]);
            CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]);
        });

        return $company;
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
