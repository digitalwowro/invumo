<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\BankAccount;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CompanyBankAccountHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_owner_manages_localized_bank_accounts_and_archives_the_default(): void
    {
        [$owner, $company] = $this->company();
        $currency = $this->currency($company, 'RON');
        $this->actingAs($owner)
            ->get(route('company-bank-accounts.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->component('companies/settings/bank-accounts')
                ->has('bankAccounts', 0)
                ->where('currencyOptions.0.value', $currency->id)
                ->where('currencyOptions.0.label', 'RON')
                ->where('routingFields.0', 'routing_number')
                ->where('routingFields.7', 'ifsc')
                ->where('companySettingsNavigation.4.key', 'bank_accounts')
                ->where('translations.settings.bank_accounts.fields.account_number', 'IBAN or account number'));

        $this->post(route('company-bank-accounts.store', $company), [
            ...$this->payload(),
            'currency_id' => $currency->id,
            'local_routing_details' => [
                'bank_code' => 'ROBU',
                'branch_code' => 'BUC-01',
            ],
            'is_default' => true,
        ])->assertRedirect()->assertSessionHas('status');
        $account = app(TenantContext::class)->runAsSystem(
            $company->id,
            fn (): BankAccount => BankAccount::query()->firstOrFail(),
        );

        $this->get(route('company-bank-accounts.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('bankAccounts.0.label', 'Main operating account')
                ->where('bankAccounts.0.currencyCode', 'RON')
                ->where('bankAccounts.0.localRoutingDetails.bank_code', 'ROBU')
                ->where('bankAccounts.0.isDefault', true)
                ->where('bankAccounts.0.archived', false));

        $this->patch(route('company-bank-accounts.update', [$company, $account]), [
            ...$this->payload('Reserve account'),
            'swift_bic' => 'BBBBROBUXXX',
            'currency_id' => null,
            'local_routing_details' => ['sort_code' => '12-34-56'],
            'is_default' => false,
        ])->assertRedirect()->assertSessionHas('status');
        $this->patch(route('company-bank-accounts.archive', [$company, $account]))
            ->assertRedirect()
            ->assertSessionHas('status');

        app(TenantContext::class)->runAsSystem($company->id, function () use ($account): void {
            $stored = BankAccount::query()->findOrFail($account->id);
            $this->assertSame('Reserve account', $stored->label);
            $this->assertSame('BBBBROBUXXX', $stored->swift_bic);
            $this->assertNull($stored->currency_id);
            $this->assertSame(['sort_code' => '12-34-56'], $stored->local_routing_details);
            $this->assertFalse($stored->is_default);
            $this->assertNotNull($stored->archived_at);
        });

        $owner->update(['language_code' => 'ro']);
        $this->get(route('company-bank-accounts.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('translations.settings.layout.navigation.bank_accounts', 'Conturi bancare')
                ->where('translations.settings.bank_accounts.routing_fields.branch_code', 'Codul sucursalei'));
    }

    public function test_admin_is_allowed_while_member_and_cross_company_access_are_denied(): void
    {
        [$owner, $company] = $this->company();
        [$outsider, $other] = $this->company('outsider@example.com', 'Other SRL');
        $admin = $this->user('admin@example.com');
        $member = $this->user('member@example.com');
        $company->memberships()->create([
            'user_id' => $admin->id, 'role' => CompanyRole::Admin,
        ]);
        $company->memberships()->create([
            'user_id' => $member->id, 'role' => CompanyRole::Member,
        ]);
        $otherAccount = app(TenantContext::class)->runAsSystem(
            $other->id,
            fn (): BankAccount => BankAccount::query()->create([
                ...$this->storedPayload('Other'), 'is_default' => false,
            ]),
        );

        $this->actingAs($admin)
            ->post(route('company-bank-accounts.store', $company), $this->payload())
            ->assertRedirect();
        $this->actingAs($member)
            ->get(route('company-bank-accounts.index', $company))
            ->assertForbidden();
        $this->post(route('company-bank-accounts.store', $company), $this->payload())
            ->assertForbidden();
        $this->actingAs($owner)
            ->get(route('company-bank-accounts.index', $other))
            ->assertNotFound();
        $this->patch(
            route('company-bank-accounts.update', [$company, $otherAccount]),
            $this->payload(),
        )->assertNotFound();
        $this->assertNotNull($outsider);
    }

    public function test_validation_rejects_unbounded_or_unknown_bank_data(): void
    {
        [$owner, $company] = $this->company();
        $otherCurrency = $this->currency($company, 'EUR');
        $this->actingAs($owner);

        $this->post(route('company-bank-accounts.store', $company), [
            'label' => '',
            'bank_name' => '',
            'account_holder' => '',
            'account_number' => '',
            'swift_bic' => 'INVALID',
            'currency_id' => 'not-a-uuid',
            'local_routing_details' => [
                'unknown' => 'value',
                'routing_number' => str_repeat('1', 65),
            ],
        ])->assertSessionHasErrors([
            'label', 'bank_name', 'account_holder', 'account_number',
            'swift_bic', 'currency_id', 'local_routing_details',
            'local_routing_details.routing_number',
        ]);

        app(TenantContext::class)->runAsSystem(
            $company->id,
            fn () => $otherCurrency->update([
                'is_default' => false,
                'active' => false,
            ]),
        );
        $this->post(route('company-bank-accounts.store', $company), [
            ...$this->payload(), 'currency_id' => $otherCurrency->id,
        ])->assertSessionHasErrors('currency_id');
    }

    /** @return array{User, Company} */
    private function company(
        string $email = 'owner@example.com',
        string $name = 'Acme SRL',
    ): array {
        $owner = $this->user($email);

        return [$owner, app(CreateCompany::class)->handle(
            $owner->account()->firstOrFail(), $owner, $name,
        )];
    }

    private function user(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        Account::query()->create(['owner_user_id' => $user->id, 'plan_id' => $plan->id]);

        return $user;
    }

    private function currency(Company $company, string $code): CompanyCurrency
    {
        return app(TenantContext::class)->runAsSystem(
            $company->id,
            fn (): CompanyCurrency => CompanyCurrency::query()->create([
                'currency_code' => $code,
                'currency_precision' => 2,
                'is_default' => true,
                'active' => true,
            ]),
        );
    }

    /** @return array<string, mixed> */
    private function payload(string $label = 'Main operating account'): array
    {
        return [
            'label' => $label,
            'bank_name' => 'Banca Exemplu',
            'account_holder' => 'Sole Trader Name',
            'account_number' => 'RO49AAAA1B31007593840000',
            'swift_bic' => 'AAAAROBUXXX',
            'is_default' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function storedPayload(string $label): array
    {
        return [
            'label' => $label,
            'bank_name' => 'Other Bank',
            'account_holder' => 'Other Holder',
            'account_number' => 'OTHER-123',
            'swift_bic' => 'BBBBROBUXXX',
        ];
    }
}
