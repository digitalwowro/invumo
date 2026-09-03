<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\BankAccount;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Customers\Models\Customer;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentBankSnapshot;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Documents\Models\DocumentTaxDefault;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Recurring\Models\RecurringTemplate;
use App\Modules\Recurring\Models\RecurringTemplateCustomerValue;
use App\Modules\Recurring\Models\RecurringTemplateDefault;
use App\Modules\Recurring\Models\RecurringTemplateLine;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class SourceLifecycleCompletionTest extends TestCase
{
    use DatabaseMigrations;

    public function test_tax_and_bank_sources_restore_and_delete_with_minimal_audit_payloads(): void
    {
        [$owner, $company] = $this->company();
        [$tax, $bank] = $this->tenant($company, fn (): array => [
            TaxPreset::query()->create(['name' => 'VAT', 'percentage' => '19']),
            BankAccount::query()->create($this->bankValues()),
        ]);
        $this->actingAs($owner);

        $this->patch(route('company-tax-presets.archive', [$company, $tax]))->assertRedirect();
        $this->patch(route('company-tax-presets.restore', [$company, $tax]))->assertRedirect();
        $this->patch(route('company-bank-accounts.archive', [$company, $bank]))->assertRedirect();
        $this->patch(route('company-bank-accounts.restore', [$company, $bank]))->assertRedirect();
        $this->delete(route('company-tax-presets.destroy', [$company, $tax]))->assertRedirect();
        $this->delete(route('company-bank-accounts.destroy', [$company, $bank]))->assertRedirect();

        $this->tenant($company, function () use ($tax, $bank): void {
            $this->assertNull(TaxPreset::query()->find($tax->id));
            $this->assertNull(BankAccount::query()->find($bank->id));

            foreach (['company.tax_preset', 'company.bank_account'] as $prefix) {
                $restored = AuditEvent::query()->where('action', "{$prefix}.restored")->sole();
                $deleted = AuditEvent::query()->where('action', "{$prefix}.deleted")->sole();
                $this->assertSame(['archived' => true], $restored->before);
                $this->assertSame(['archived' => false], $restored->after);
                $this->assertSame(['deleted' => false], $deleted->before);
                $this->assertSame(['deleted' => true], $deleted->after);
            }
        });
    }

    public function test_dependency_warnings_match_the_action_guards(): void
    {
        [$owner, $company] = $this->company();
        [$tax, $bank, $customer, $product] = $this->dependentSources($company);
        $this->actingAs($owner);

        $this->get(route('company-tax-presets.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('taxPresets.0.archiveGuard.blocked', true)
                ->where('taxPresets.0.deleteGuard.blocked', true)
                ->where('taxPresets.0.deleteGuard.description', fn (mixed $value): bool => is_string($value)
                    && str_contains($value, 'document snapshots or lines — 2')
                    && str_contains($value, 'recurring templates — 2')));
        $this->get(route('company-bank-accounts.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('bankAccounts.0.deleteGuard.blocked', true)
                ->where('bankAccounts.0.deleteGuard.description', fn (mixed $value): bool => is_string($value) && str_contains($value, 'recurring templates — 1')));
        $this->get(route('customers.show', [$company, $customer]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('deleteGuard.blocked', true)
                ->where('deleteGuard.description', fn (mixed $value): bool => is_string($value) && str_contains($value, 'documents — 1')));
        $this->get(route('catalog.show', [$company, $product]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.id', $product->id)
                ->where('deleteGuard.blocked', true));

        $this->delete(route('customers.destroy', [$company, $customer]))
            ->assertSessionHasErrors('customer');
        $this->delete(route('catalog.destroy', [$company, $product]))
            ->assertSessionHasErrors('product_service');
        $this->patch(route('company-tax-presets.archive', [$company, $tax]))
            ->assertSessionHasErrors('tax_preset');
        $this->delete(route('company-tax-presets.destroy', [$company, $tax]))
            ->assertSessionHasErrors('tax_preset');
        $this->delete(route('company-bank-accounts.destroy', [$company, $bank]))
            ->assertSessionHasErrors('bank_account');
    }

    public function test_member_and_cross_company_source_lifecycle_access_is_denied(): void
    {
        [$owner, $company] = $this->company();
        [, $other] = $this->company('other@example.com');
        $member = $this->user('member@example.com');
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        [$tax, $bank] = $this->tenant($other, fn (): array => [
            TaxPreset::query()->create(['name' => 'Other VAT', 'percentage' => '20', 'archived_at' => now()]),
            BankAccount::query()->create([...$this->bankValues(), 'archived_at' => now()]),
        ]);

        $this->actingAs($member)
            ->patch(route('company-tax-presets.restore', [$company, $tax]))
            ->assertForbidden();
        $this->delete(route('company-bank-accounts.destroy', [$company, $bank]))
            ->assertForbidden();
        $this->actingAs($owner)
            ->patch(route('company-tax-presets.restore', [$company, $tax]))
            ->assertNotFound();
        $this->delete(route('company-bank-accounts.destroy', [$company, $bank]))
            ->assertNotFound();
    }

    public function test_bank_restore_rechecks_its_optional_currency_source(): void
    {
        [$owner, $company] = $this->company();
        [$currency, $bank] = $this->tenant($company, function (): array {
            $currency = CompanyCurrency::query()->create([
                'currency_code' => 'EUR', 'currency_precision' => 2,
                'is_default' => false, 'active' => true,
            ]);

            return [$currency, BankAccount::query()->create([
                ...$this->bankValues(), 'currency_id' => $currency->id,
            ])];
        });
        $this->actingAs($owner)
            ->patch(route('company-bank-accounts.archive', [$company, $bank]))
            ->assertRedirect();
        $this->tenant($company, fn () => $currency->update(['active' => false]));

        $this->patch(route('company-bank-accounts.restore', [$company, $bank]))
            ->assertSessionHasErrors('bank_account');
        $this->tenant($company, fn () => $this->assertNotNull(
            BankAccount::query()->findOrFail($bank->id)->archived_at,
        ));
    }

    public function test_recurring_line_tax_provenance_blocks_action_and_database_deletion(): void
    {
        [$owner, $company] = $this->company();
        [$tax, $line] = $this->tenant($company, function (): array {
            $tax = TaxPreset::query()->create(['name' => 'VAT', 'percentage' => '19']);
            $customer = Customer::query()->create([
                'type' => 'COMPANY', 'legal_name' => 'Recurring Customer SRL',
            ]);
            $template = RecurringTemplate::query()->create([
                'client_creation_key' => (string) Str::uuid7(),
                'internal_name' => 'Tax provenance', 'customer_id' => $customer->id,
            ]);
            $line = RecurringTemplateLine::query()->create([
                'recurring_template_id' => $template->id, 'position' => 1,
                'description' => 'Taxed service', 'tax_mode' => 'EXPLICIT',
                'tax_preset_id' => $tax->id, 'tax_name' => 'VAT', 'tax_percentage' => '19',
            ]);

            return [$tax, $line];
        });

        $this->actingAs($owner)
            ->delete(route('company-tax-presets.destroy', [$company, $tax]))
            ->assertSessionHasErrors('tax_preset');

        try {
            $this->tenant($company, fn () => TaxPreset::query()->whereKey($tax->id)->delete());
            $this->fail('Recurring line tax provenance must restrict preset deletion.');
        } catch (QueryException $exception) {
            $this->assertContains($exception->errorInfo[0] ?? null, ['23001', '23503']);
        }

        $this->tenant($company, function () use ($tax, $line): void {
            $this->assertNotNull(TaxPreset::query()->find($tax->id));
            $this->assertSame($tax->id, RecurringTemplateLine::query()->findOrFail($line->id)->tax_preset_id);
        });
    }

    /** @return array{TaxPreset, BankAccount, Customer, ProductService} */
    private function dependentSources(Company $company): array
    {
        return $this->tenant($company, function (): array {
            $tax = TaxPreset::query()->create(['name' => 'VAT', 'percentage' => '19']);
            $bank = BankAccount::query()->create($this->bankValues());
            $customer = Customer::query()->create([
                'type' => 'COMPANY', 'legal_name' => 'Customer SRL', 'tax_preset_id' => $tax->id,
            ]);
            $product = ProductService::query()->create([
                'name' => 'Service', 'tax_preset_id' => $tax->id,
            ]);
            $document = Document::query()->create([
                'kind' => 'QUOTE', 'customer_id' => $customer->id, 'rendered_number' => 'DRAFT',
                'assignment_source' => 'MANUAL', 'client_creation_key' => (string) Str::uuid7(),
            ]);
            Quote::query()->create(['document_id' => $document->id]);
            DocumentLine::query()->create([
                'document_id' => $document->id, 'position' => 1,
                'product_service_id' => $product->id, 'tax_preset_id' => $tax->id,
            ]);
            DocumentTaxDefault::query()->create([
                'document_id' => $document->id, 'tax_preset_id' => $tax->id,
                'name' => 'VAT', 'percentage' => '19',
            ]);
            DocumentBankSnapshot::query()->create([
                'document_id' => $document->id, 'bank_account_id' => $bank->id,
                'label' => $bank->label, 'bank_name' => $bank->bank_name,
                'account_holder' => $bank->account_holder, 'account_number' => $bank->account_number,
            ]);
            $template = RecurringTemplate::query()->create([
                'client_creation_key' => (string) Str::uuid7(), 'internal_name' => 'Template',
                'customer_id' => $customer->id,
            ]);
            RecurringTemplateCustomerValue::query()->create([
                'recurring_template_id' => $template->id, 'explicit_fields' => ['tax_default'],
                'tax_preset_id' => $tax->id, 'tax_name' => 'VAT', 'tax_percentage' => '19',
            ]);
            RecurringTemplateLine::query()->create([
                'recurring_template_id' => $template->id, 'position' => 1,
                'description' => 'Taxed recurring line', 'tax_mode' => 'EXPLICIT',
                'tax_preset_id' => $tax->id, 'tax_name' => 'VAT', 'tax_percentage' => '19',
            ]);
            RecurringTemplateDefault::query()->create([
                'recurring_template_id' => $template->id, 'bank_mode' => 'EXPLICIT',
                'bank_account_id' => $bank->id, 'bank_label' => $bank->label,
                'bank_name' => $bank->bank_name, 'bank_account_holder' => $bank->account_holder,
                'bank_account_number' => $bank->account_number,
            ]);

            return [$tax, $bank, $customer, $product];
        });
    }

    /** @return array<string, mixed> */
    private function bankValues(): array
    {
        return [
            'label' => 'Main', 'bank_name' => 'Bank', 'account_holder' => 'Holder',
            'account_number' => 'RO49AAAA1B31007593840000', 'swift_bic' => null,
        ];
    }

    /** @return array{User, Company} */
    private function company(string $email = 'owner@example.com'): array
    {
        $owner = $this->user($email);

        return [$owner, app(CreateCompany::class)->handle(
            $owner->account()->firstOrFail(), $owner, 'Lifecycle SRL',
        )];
    }

    private function user(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        Account::query()->create([
            'owner_user_id' => $user->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);

        return $user;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
