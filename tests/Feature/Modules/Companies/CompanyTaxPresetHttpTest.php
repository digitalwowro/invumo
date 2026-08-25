<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CompanyTaxPresetHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_owner_manages_tax_presets_with_localized_page_props(): void
    {
        $owner = $this->accountOwner();
        $company = $this->companyFor($owner);

        $this->actingAs($owner)
            ->get(route('company-tax-presets.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->component('companies/settings/taxes')
                ->has('taxPresets', 0)
                ->where('companySettingsNavigation.0.key', 'profile')
                ->where('companySettingsNavigation.1.key', 'documents')
                ->where('companySettingsNavigation.2.key', 'numbering')
                ->where('companySettingsNavigation.3.key', 'taxes')
                ->where('companySettingsNavigation.4.key', 'bank_accounts')
                ->where('companySettingsNavigation.5.key', 'appearance')
                ->where('companySettingsNavigation.6.key', 'members')
                ->where('translations.settings.taxes.fields.percentage', 'Percentage'));

        $this->post(route('company-tax-presets.store', $company), [
            'name' => 'Standard VAT',
            'percentage' => '19',
            'is_default' => true,
        ])->assertRedirect()->assertSessionHas('status');

        $preset = app(TenantContext::class)->runAsSystem(
            $company->id,
            fn (): TaxPreset => TaxPreset::query()->firstOrFail(),
        );

        $this->get(route('company-tax-presets.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('taxPresets.0.name', 'Standard VAT')
                ->where('taxPresets.0.percentage', '19')
                ->where('taxPresets.0.isDefault', true)
                ->where('taxPresets.0.archived', false));

        $this->patch(route('company-tax-presets.update', [$company, $preset]), [
            'name' => 'Standard VAT 20',
            'percentage' => '20.000000',
            'is_default' => false,
        ])->assertRedirect()->assertSessionHas('status');

        $this->patch(route('company-tax-presets.archive', [$company, $preset]))
            ->assertRedirect()
            ->assertSessionHas('status');

        app(TenantContext::class)->runAsSystem($company->id, function () use ($preset): void {
            $stored = TaxPreset::query()->findOrFail($preset->id);
            $this->assertSame('20.000000', $stored->percentage);
            $this->assertFalse($stored->is_default);
            $this->assertNotNull($stored->archived_at);
        });

        $owner->update(['language_code' => 'ro']);
        $this->get(route('company-tax-presets.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('translations.settings.layout.navigation.taxes', 'Taxe')
                ->where('translations.settings.taxes.active', 'Activă'));
    }

    public function test_default_switching_is_atomic_and_no_op_updates_do_not_audit(): void
    {
        $owner = $this->accountOwner();
        $company = $this->companyFor($owner);
        $this->actingAs($owner);

        $this->post(route('company-tax-presets.store', $company), [
            'name' => 'Standard', 'percentage' => '19', 'is_default' => true,
        ])->assertRedirect();
        $this->post(route('company-tax-presets.store', $company), [
            'name' => 'Reduced', 'percentage' => '9', 'is_default' => true,
        ])->assertRedirect();

        $reduced = app(TenantContext::class)->runAsSystem(
            $company->id,
            function (): TaxPreset {
                $standard = TaxPreset::query()->where('name', 'Standard')->firstOrFail();
                $reduced = TaxPreset::query()->where('name', 'Reduced')->firstOrFail();
                $this->assertFalse($standard->is_default);
                $this->assertTrue($reduced->is_default);
                $this->assertSame(1, TaxPreset::query()->where('is_default', true)->count());

                return $reduced;
            },
        );
        $this->patch(route('company-tax-presets.update', [$company, $reduced]), [
            'name' => 'Reduced', 'percentage' => '9', 'is_default' => true,
        ])->assertRedirect();

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            $this->assertSame(
                2,
                AuditEvent::query()->where('action', 'company.tax_preset.created')->count(),
            );
            $this->assertSame(
                0,
                AuditEvent::query()->where('action', 'company.tax_preset.updated')->count(),
            );
        });
    }

    public function test_default_reassignment_locks_tax_presets_in_stable_uuid_order(): void
    {
        $owner = $this->accountOwner();
        $company = $this->companyFor($owner);
        $this->actingAs($owner);

        $presets = app(TenantContext::class)->runAsSystem(
            $company->id,
            fn () => collect([
                TaxPreset::query()->create([
                    'name' => 'Standard', 'percentage' => '19', 'is_default' => true,
                ]),
                TaxPreset::query()->create([
                    'name' => 'Reduced', 'percentage' => '9', 'is_default' => false,
                ]),
            ]),
        );
        $queries = [];
        DB::connection(config('database.tenant_connection'))->listen(
            static function (QueryExecuted $query) use (&$queries): void {
                $queries[] = $query->sql;
            },
        );

        $this->patch(route('company-tax-presets.update', [$company, $presets[1]]), [
            'name' => 'Reduced', 'percentage' => '9', 'is_default' => true,
        ])->assertRedirect();
        $this->assertOrderedPresetLockPrecedesMutation($queries);

        $queries = [];
        $this->post(route('company-tax-presets.store', $company), [
            'name' => 'Zero', 'percentage' => '0', 'is_default' => true,
        ])->assertRedirect();
        $this->assertOrderedPresetLockPrecedesMutation($queries);
    }

    public function test_admin_is_allowed_while_member_and_cross_company_access_are_denied(): void
    {
        $owner = $this->accountOwner();
        $admin = $this->accountOwner('admin@example.com');
        $member = $this->accountOwner('member@example.com');
        $outsider = $this->accountOwner('outsider@example.com');
        $company = $this->companyFor($owner);
        $other = $this->companyFor($outsider, 'Other SRL');
        $this->addMember($company, $admin, CompanyRole::Admin);
        $this->addMember($company, $member, CompanyRole::Member);
        $otherPreset = app(TenantContext::class)->runAsSystem(
            $other->id,
            fn (): TaxPreset => TaxPreset::query()->create([
                'name' => 'Other rate', 'percentage' => '20', 'is_default' => false,
            ]),
        );

        $this->actingAs($admin)->post(route('company-tax-presets.store', $company), [
            'name' => 'Admin rate', 'percentage' => '5', 'is_default' => false,
        ])->assertRedirect();

        $this->actingAs($member)
            ->get(route('company-tax-presets.index', $company))
            ->assertForbidden();
        $this->post(route('company-tax-presets.store', $company), [
            'name' => 'Forbidden', 'percentage' => '1', 'is_default' => false,
        ])->assertForbidden();

        $this->actingAs($owner)
            ->get(route('company-tax-presets.index', $other))
            ->assertNotFound();
        $this->patch(route('company-tax-presets.update', [$company, $otherPreset->id]), [
            'name' => 'Cross-company edit', 'percentage' => '1', 'is_default' => false,
        ])->assertNotFound();
    }

    public function test_validation_and_archived_state_use_the_active_locale(): void
    {
        $owner = $this->accountOwner();
        $owner->update(['language_code' => 'ro']);
        $company = $this->companyFor($owner);
        $this->actingAs($owner);

        $response = $this->post(route('company-tax-presets.store', $company), [
            'name' => '', 'percentage' => '-1.0000001', 'is_default' => false,
        ]);
        $response->assertSessionHasErrors(['name', 'percentage']);
        $this->assertStringContainsString(
            'număr nenegativ',
            $response->getSession()->get('errors')->first('percentage'),
        );

        $this->post(route('company-tax-presets.store', $company), [
            'name' => 'TVA', 'percentage' => '19', 'is_default' => true,
        ])->assertRedirect();
        $preset = app(TenantContext::class)->runAsSystem(
            $company->id,
            fn (): TaxPreset => TaxPreset::query()->firstOrFail(),
        );
        $this->patch(route('company-tax-presets.archive', [$company, $preset]))
            ->assertRedirect();
        $this->patch(route('company-tax-presets.update', [$company, $preset]), [
            'name' => 'TVA nou', 'percentage' => '20', 'is_default' => false,
        ])->assertSessionHasErrors('tax_preset');
    }

    private function accountOwner(string $email = 'owner@example.com'): User
    {
        $user = User::factory()->create(['email' => $email]);
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        Account::query()->create(['owner_user_id' => $user->id, 'plan_id' => $plan->id]);

        return $user;
    }

    private function companyFor(User $owner, string $name = 'Acme SRL'): Company
    {
        return app(CreateCompany::class)->handle(
            $owner->account()->firstOrFail(),
            $owner,
            $name,
        );
    }

    private function addMember(Company $company, User $user, CompanyRole $role): CompanyMembership
    {
        return $company->memberships()->create(['user_id' => $user->id, 'role' => $role]);
    }

    /** @param list<string> $queries */
    private function assertOrderedPresetLockPrecedesMutation(array $queries): void
    {
        $lockIndex = null;
        $mutationIndex = null;

        foreach ($queries as $index => $query) {
            if (
                str_contains($query, 'from "tax_presets"')
                && str_contains($query, 'order by "id" asc')
                && str_contains($query, 'for update')
            ) {
                $lockIndex ??= $index;
            }

            if (
                str_starts_with($query, 'update "tax_presets"')
                || str_starts_with($query, 'insert into "tax_presets"')
            ) {
                $mutationIndex ??= $index;
            }
        }

        $this->assertNotNull($lockIndex, 'Missing ordered tax preset lock query.');
        $this->assertNotNull($mutationIndex, 'Missing tax preset mutation query.');
        $this->assertLessThan($mutationIndex, $lockIndex);
    }
}
