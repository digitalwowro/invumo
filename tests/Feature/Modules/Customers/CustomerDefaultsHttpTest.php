<?php

namespace Tests\Feature\Modules\Customers;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerContact;
use App\Modules\Customers\Models\CustomerDeliveryRecipient;
use App\Modules\Customers\Queries\CustomerDefaultResolution;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CustomerDefaultsHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_member_sets_overrides_and_resolution_uses_current_customer_then_company_values(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $company = $this->companyFor($owner);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $records = $this->configuredCustomer($company);
        $this->actingAs($member);

        $this->get(route('customer-defaults.index', [$company, $records['customer']]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('customers/defaults')
                ->where('defaults.currencyId', null)
                ->where('resolvedDefaults.currency.code', 'RON')
                ->where('resolvedDefaults.currency.source', 'COMPANY')
                ->where('resolvedDefaults.documentLanguage.value', 'en')
                ->where('resolvedDefaults.paymentTermDays.value', '30')
                ->where('resolvedDefaults.taxPreset.percentage', '19')
                ->where('resolvedDefaults.emailAttachmentMode.value', 'SECURE_LINK_ONLY')
                ->where('resolvedDefaults.recipients.count', 1)
                ->missing('resolvedDefaults.recipients.items')
                ->where('translations.defaults.title', 'Customer defaults'));

        $this->tenant($company, function () use ($records): void {
            $resolved = app(CustomerDefaultResolution::class)->for($records['customer']);
            $this->assertSame('billing@example.com', $resolved['recipients']['items'][0]['email']);
        });

        $overrides = [
            'currency_id' => $records['eur']->id,
            'document_language' => 'ro',
            'payment_term_days' => '45',
            'tax_preset_id' => $records['reducedTax']->id,
        ];
        $this->patch(
            route('customer-defaults.update', [$company, $records['customer']]),
            $overrides,
        )->assertRedirect()->assertSessionHas('status');
        $this->patch(
            route('customer-defaults.update', [$company, $records['customer']]),
            $overrides,
        )->assertRedirect();

        $this->get(route('customer-defaults.index', [$company, $records['customer']]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('resolvedDefaults.currency.code', 'EUR')
                ->where('resolvedDefaults.currency.precision', 2)
                ->where('resolvedDefaults.currency.source', 'CUSTOMER')
                ->where('resolvedDefaults.documentLanguage.value', 'ro')
                ->where('resolvedDefaults.documentLanguage.source', 'CUSTOMER')
                ->where('resolvedDefaults.paymentTermDays.value', '45')
                ->where('resolvedDefaults.taxPreset.percentage', '5')
                ->where('resolvedDefaults.taxPreset.source', 'CUSTOMER'));

        $this->tenant($company, function () use ($records): void {
            $event = AuditEvent::query()
                ->where('action', 'company.customer_defaults.updated')
                ->sole();
            $this->assertSame([
                'changed_fields', 'currency_code', 'document_language',
                'payment_term_days', 'tax_percentage',
            ], $this->sortedKeys($event->after));
            $encoded = $event->toJson();
            $this->assertStringNotContainsString('Client SRL', $encoded);
            $this->assertStringNotContainsString('billing@example.com', $encoded);

            CompanyCurrency::query()->findOrFail($records['eur']->id)->update(['active' => false]);
            TaxPreset::query()->findOrFail($records['reducedTax']->id)->update(['archived_at' => now()]);
        });

        $this->get(route('customer-defaults.index', [$company, $records['customer']]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('resolvedDefaults.currency.code', 'RON')
                ->where('resolvedDefaults.currency.source', 'COMPANY')
                ->where('resolvedDefaults.taxPreset.percentage', '19')
                ->where('resolvedDefaults.taxPreset.source', 'COMPANY'));

        $this->patch(route('customer-defaults.update', [$company, $records['customer']]), [
            'currency_id' => 'INHERIT',
            'document_language' => 'INHERIT',
            'payment_term_days' => '',
            'tax_preset_id' => 'INHERIT',
        ])->assertRedirect();
        $this->tenant($company, function () use ($records): void {
            $customer = Customer::query()->findOrFail($records['customer']->id);
            $this->assertNull($customer->currency_id);
            $this->assertNull($customer->document_language);
            $this->assertNull($customer->payment_term_days);
            $this->assertNull($customer->tax_preset_id);
        });
    }

    public function test_defaults_reject_unavailable_values_and_archived_customers(): void
    {
        $owner = User::factory()->create(['language_code' => 'ro']);
        $otherOwner = User::factory()->create();
        $company = $this->companyFor($owner);
        $otherCompany = $this->companyFor($otherOwner);
        $records = $this->configuredCustomer($company);
        $otherCurrency = $this->tenant($otherCompany, fn (): CompanyCurrency => CompanyCurrency::query()->create([
            'currency_code' => 'USD', 'currency_precision' => 2, 'is_default' => true, 'active' => true,
        ]));
        $this->actingAs($owner);

        $this->tenant($company, function () use ($records): void {
            $records['eur']->update(['active' => false]);
            $records['reducedTax']->update(['archived_at' => now()]);
        });

        $url = route('customer-defaults.update', [$company, $records['customer']]);
        $this->patch($url, $this->payload(currencyId: $records['eur']->id))
            ->assertSessionHasErrors('currency_id');
        $this->patch($url, $this->payload(currencyId: $otherCurrency->id))
            ->assertSessionHasErrors('currency_id');
        $this->patch($url, $this->payload(taxPresetId: $records['reducedTax']->id))
            ->assertSessionHasErrors('tax_preset_id');
        $this->patch($url, $this->payload(language: 'de'))
            ->assertSessionHasErrors('document_language');
        $this->patch($url, $this->payload(paymentTerm: '3652059'))
            ->assertSessionHasErrors('payment_term_days');

        $this->tenant($company, fn () => $records['customer']->update(['archived_at' => now()]));
        $this->patch($url, $this->payload())
            ->assertSessionHasErrors('defaults');
    }

    /**
     * @return array{
     *     customer: Customer,
     *     ron: CompanyCurrency,
     *     eur: CompanyCurrency,
     *     defaultTax: TaxPreset,
     *     reducedTax: TaxPreset
     * }
     */
    private function configuredCustomer(Company $company): array
    {
        return $this->tenant($company, function (): array {
            CompanySetting::query()->firstOrFail()->update([
                'default_document_language' => 'en',
                'default_payment_term_days' => 30,
            ]);
            $ron = CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2, 'is_default' => true, 'active' => true,
            ]);
            $eur = CompanyCurrency::query()->create([
                'currency_code' => 'EUR', 'currency_precision' => 2, 'is_default' => false, 'active' => true,
            ]);
            $defaultTax = TaxPreset::query()->create([
                'name' => 'Standard VAT', 'percentage' => '19', 'is_default' => true,
            ]);
            $reducedTax = TaxPreset::query()->create([
                'name' => 'Reduced VAT', 'percentage' => '5', 'is_default' => false,
            ]);
            $customer = Customer::query()->create(['type' => 'COMPANY', 'legal_name' => 'Client SRL']);
            $contact = CustomerContact::query()->create([
                'customer_id' => $customer->id,
                'name' => 'Billing Person',
                'email' => 'billing@example.com',
                'display_order' => 0,
            ]);
            CustomerDeliveryRecipient::query()->create([
                'customer_id' => $customer->id,
                'role' => 'TO',
                'contact_id' => $contact->id,
                'display_order' => 0,
            ]);

            return compact('customer', 'ron', 'eur', 'defaultTax', 'reducedTax');
        });
    }

    /** @return array<string, mixed> */
    private function payload(
        ?string $currencyId = null,
        ?string $language = null,
        ?string $paymentTerm = null,
        ?string $taxPresetId = null,
    ): array {
        return [
            'currency_id' => $currencyId,
            'document_language' => $language,
            'payment_term_days' => $paymentTerm,
            'tax_preset_id' => $taxPresetId,
        ];
    }

    private function companyFor(User $owner): Company
    {
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);

        return app(CreateCompany::class)->handle($account, $owner, 'Defaults Test SRL');
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

    /**
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function sortedKeys(array $values): array
    {
        $keys = array_keys($values);
        sort($keys);

        return $keys;
    }
}
