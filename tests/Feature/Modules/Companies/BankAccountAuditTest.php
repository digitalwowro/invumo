<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\BankAccount;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

final class BankAccountAuditTest extends TestCase
{
    use DatabaseMigrations;

    public function test_audit_keeps_operational_state_but_excludes_all_bank_values(): void
    {
        [$owner, $company] = $this->company();
        [$ron, $eur] = app(TenantContext::class)->runAsSystem(
            $company->id,
            fn (): array => [
                $this->currency('RON'),
                $this->currency('EUR'),
            ],
        );
        $this->actingAs($owner)->post(
            route('company-bank-accounts.store', $company),
            $this->payload($ron->id, true),
        )->assertRedirect();
        $account = app(TenantContext::class)->runAsSystem(
            $company->id,
            fn (): BankAccount => BankAccount::query()->firstOrFail(),
        );

        $this->patch(
            route('company-bank-accounts.update', [$company, $account]),
            $this->payload($eur->id, false, 'Private replacement'),
        )->assertRedirect();
        $this->patch(route('company-bank-accounts.archive', [$company, $account]))
            ->assertRedirect();

        app(TenantContext::class)->runAsSystem($company->id, function () use (
            $account,
            $eur,
        ): void {
            $created = AuditEvent::query()
                ->where('action', 'company.bank_account.created')
                ->where('target_id', $account->id)
                ->firstOrFail();
            $updated = AuditEvent::query()
                ->where('action', 'company.bank_account.updated')
                ->where('target_id', $account->id)
                ->firstOrFail();
            $archived = AuditEvent::query()
                ->where('action', 'company.bank_account.archived')
                ->where('target_id', $account->id)
                ->firstOrFail();

            $this->assertEqualsCanonicalizing(
                ['changed_fields', 'currency_code', 'is_default'],
                array_keys($created->after ?? []),
            );
            $this->assertSame('RON', $created->after['currency_code']);
            $this->assertSame('EUR', $updated->after['currency_code']);
            $this->assertSame($eur->id, $account->fresh()?->currency_id);
            $this->assertEqualsCanonicalizing(
                ['changed_fields', 'currency_code', 'is_default'],
                array_keys($updated->before ?? []),
            );
            $this->assertEqualsCanonicalizing(
                ['changed_fields', 'is_default', 'archived'],
                array_keys($archived->after ?? []),
            );
            $this->assertSensitiveValuesAbsent([$created, $updated, $archived]);
            $this->assertContains('account_number', $updated->after['changed_fields']);
            $this->assertContains('swift_bic', $updated->after['changed_fields']);
            $this->assertContains(
                'local_routing_details',
                $updated->after['changed_fields'],
            );
        });
    }

    public function test_no_op_update_does_not_create_an_audit_event(): void
    {
        [$owner, $company] = $this->company();
        $currency = app(TenantContext::class)->runAsSystem(
            $company->id,
            fn (): CompanyCurrency => $this->currency('RON'),
        );
        $payload = $this->payload($currency->id, true);
        $this->actingAs($owner)
            ->post(route('company-bank-accounts.store', $company), $payload)
            ->assertRedirect();
        $account = app(TenantContext::class)->runAsSystem(
            $company->id,
            fn (): BankAccount => BankAccount::query()->firstOrFail(),
        );

        $this->patch(
            route('company-bank-accounts.update', [$company, $account]),
            $payload,
        )->assertRedirect();

        app(TenantContext::class)->runAsSystem(
            $company->id,
            fn () => $this->assertSame(
                0,
                AuditEvent::query()
                    ->where('action', 'company.bank_account.updated')
                    ->count(),
            ),
        );
    }

    /** @param array<int, AuditEvent> $events */
    private function assertSensitiveValuesAbsent(array $events): void
    {
        $payload = strtolower(json_encode(
            array_map(
                fn (AuditEvent $event): array => [$event->before, $event->after],
                $events,
            ),
            JSON_THROW_ON_ERROR,
        ));

        foreach ([
            'private account', 'private replacement', 'sole trader person',
            'private bank', 'ro49aaaa1b31007593840000', 'aaaarobuxxx',
            'private-routing-value',
        ] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $payload);
        }
    }

    /** @return array{User, Company} */
    private function company(): array
    {
        $owner = User::factory()->create();
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id, 'plan_id' => $plan->id,
        ]);

        return [$owner, app(CreateCompany::class)->handle($account, $owner, 'Acme SRL')];
    }

    private function currency(string $code): CompanyCurrency
    {
        return CompanyCurrency::query()->create([
            'currency_code' => $code,
            'currency_precision' => 2,
            'is_default' => $code === 'RON',
            'active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(
        string $currencyId,
        bool $default,
        string $label = 'Private account',
    ): array {
        return [
            'label' => $label,
            'bank_name' => 'Private Bank',
            'account_holder' => 'Sole Trader Person',
            'account_number' => $label === 'Private account'
                ? 'RO49AAAA1B31007593840000'
                : 'REPLACEMENT-123',
            'swift_bic' => $label === 'Private account' ? null : 'AAAAROBUXXX',
            'currency_id' => $currencyId,
            'local_routing_details' => [
                'routing_number' => $label === 'Private account'
                    ? 'private-routing-value'
                    : 'replacement-routing-value',
            ],
            'is_default' => $default,
        ];
    }
}
