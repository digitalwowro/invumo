<?php

namespace Tests\Feature\Modules\Delivery;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Models\CompanyReminderRule;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CompanyReminderRulesHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_owner_and_admin_manage_rules_with_safe_audit_metadata(): void
    {
        [$owner, $company] = $this->company();
        $admin = User::factory()->create();
        $company->memberships()->create(['user_id' => $admin->id, 'role' => CompanyRole::Admin]);

        $this->actingAs($owner)
            ->get(route('company-reminder-rules.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->component('companies/settings/reminders')
                ->has('rules', 0)
                ->where('limits.rules', 20)
                ->where('companySettingsNavigation.3.key', 'reminders'));

        $this->actingAs($admin)->put(route('company-reminder-rules.update', $company), [
            'rules' => [
                ['relation' => 'BEFORE_DUE', 'day_offset' => 3, 'enabled' => true],
                ['relation' => 'AFTER_DUE', 'day_offset' => 7, 'enabled' => false],
            ],
        ])->assertRedirect()->assertSessionHas('status');

        $this->tenant($company, function (): void {
            $this->assertSame(2, CompanyReminderRule::query()->count());
            $audit = AuditEvent::query()->where('action', 'company.reminder_rules.updated')->sole();
            $this->assertSame(['rule_count' => 2, 'enabled_count' => 1], $audit->after);
        });
    }

    public function test_validation_authorization_and_rls_fail_closed(): void
    {
        [$owner, $company] = $this->company();
        [$outsider, $otherCompany] = $this->company();
        $member = User::factory()->create();
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $duplicate = ['rules' => [
            ['relation' => 'BEFORE_DUE', 'day_offset' => 1, 'enabled' => true],
            ['relation' => 'BEFORE_DUE', 'day_offset' => 1, 'enabled' => true],
        ]];

        $this->actingAs($owner)
            ->put(route('company-reminder-rules.update', $company), $duplicate)
            ->assertSessionHasErrors('rules');
        $this->actingAs($member)
            ->get(route('company-reminder-rules.index', $company))->assertForbidden();
        $this->put(route('company-reminder-rules.update', $company), ['rules' => [[
            'relation' => 'AFTER_DUE', 'day_offset' => 1, 'enabled' => true,
        ]]])
            ->assertForbidden();
        $this->actingAs($outsider)
            ->get(route('company-reminder-rules.index', $company))->assertNotFound();
        $this->actingAs($owner)
            ->get(route('company-reminder-rules.index', $otherCompany))->assertNotFound();

        foreach (['company_reminder_rules', 'document_reminder_rules', 'reminder_instances'] as $table) {
            $rls = DB::connection('pgsql_schema')->selectOne(<<<SQL
                SELECT relrowsecurity, relforcerowsecurity
                FROM pg_class WHERE oid = 'public.{$table}'::regclass
                SQL);
            $this->assertTrue($rls->relrowsecurity, $table);
            $this->assertTrue($rls->relforcerowsecurity, $table);
            $this->assertSame(0, DB::connection('pgsql_schema')->table($table)->count(), $table);
        }
    }

    /** @return array{User, Company} */
    private function company(): array
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);

        return [$owner, app(CreateCompany::class)->handle($account, $owner, 'Reminder Company SRL')];
    }

    private function tenant(Company $company, callable $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
