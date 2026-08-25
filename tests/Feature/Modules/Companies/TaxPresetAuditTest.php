<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

final class TaxPresetAuditTest extends TestCase
{
    use DatabaseMigrations;

    public function test_audit_payloads_keep_operational_values_but_never_the_preset_name(): void
    {
        [$owner, $company] = $this->company();
        $this->actingAs($owner)->post(route('company-tax-presets.store', $company), [
            'name' => 'Sole trader private label',
            'percentage' => '19.125',
            'is_default' => true,
        ])->assertRedirect();
        $preset = app(TenantContext::class)->runAsSystem(
            $company->id,
            fn (): TaxPreset => TaxPreset::query()->firstOrFail(),
        );

        $this->patch(route('company-tax-presets.update', [$company, $preset]), [
            'name' => 'Another private label',
            'percentage' => '20',
            'is_default' => false,
        ])->assertRedirect();
        $this->patch(route('company-tax-presets.archive', [$company, $preset]))
            ->assertRedirect();

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            $events = AuditEvent::query()
                ->whereIn('action', [
                    'company.tax_preset.created',
                    'company.tax_preset.updated',
                    'company.tax_preset.archived',
                ])
                ->orderBy('occurred_at')
                ->get();

            $this->assertCount(3, $events);
            $this->assertEqualsCanonicalizing(
                ['changed_fields', 'percentage', 'is_default'],
                array_keys($events[0]->after ?? []),
            );
            $this->assertEqualsCanonicalizing(
                ['changed_fields', 'percentage', 'is_default'],
                array_keys($events[1]->before ?? []),
            );
            $this->assertEqualsCanonicalizing(
                ['changed_fields', 'is_default', 'archived'],
                array_keys($events[2]->after ?? []),
            );
            $payload = json_encode(
                $events->map(fn (AuditEvent $event): array => [
                    $event->before, $event->after,
                ])->all(),
                JSON_THROW_ON_ERROR,
            );
            $this->assertStringNotContainsString('private label', strtolower($payload));
            $this->assertContains('name', $events[0]->after['changed_fields']);
            $this->assertContains('name', $events[1]->after['changed_fields']);
        });
    }

    /** @return array{User, Company} */
    private function company(): array
    {
        $owner = User::factory()->create();
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => $plan->id,
        ]);

        return [$owner, app(CreateCompany::class)->handle($account, $owner, 'Acme SRL')];
    }
}
