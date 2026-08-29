<?php

namespace Tests\Feature\Modules\Recurring;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Recurring\Models\RecurringTemplate;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class RecurringAutomationOperationsTest extends TestCase
{
    use DatabaseMigrations;

    public function test_failed_active_templates_are_filtered_and_visible_company_wide(): void
    {
        [$owner, $member, $company, $failed] = $this->records();

        $this->actingAs($owner)
            ->get(route('recurring.index', [$company, 'outcome' => 'failed']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.outcome', 'failed')
                ->has('templates.items', 1)
                ->where('templates.items.0.id', $failed->id)
                ->where('companyContext.automation.failedRecurringCount', 1)
                ->where(
                    'companyContext.automation.failedRecurringUrl',
                    route('recurring.index', [
                        'company' => $company,
                        'outcome' => 'failed',
                    ], false),
                ));

        $this->actingAs($member)
            ->get(route('recurring.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('companyContext.automation', null));
    }

    public function test_owner_controls_automatic_email_with_confirmation_and_audit(): void
    {
        [$owner, $member, $company, $template] = $this->records();
        $payload = [
            'edit_version' => 1,
            'automatic_email_enabled' => true,
            'confirmed' => true,
        ];

        $this->actingAs($member)
            ->patch(route('recurring.automatic-email.update', [$company, $template]), $payload)
            ->assertForbidden();
        $this->actingAs($owner)
            ->patch(route('recurring.automatic-email.update', [$company, $template]), [
                ...$payload, 'confirmed' => false,
            ])->assertSessionHasErrors('confirmed');
        $this->patch(
            route('recurring.automatic-email.update', [$company, $template]),
            $payload,
        )->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->tenant($company, function () use ($template): void {
            $this->assertTrue($template->refresh()->automatic_email_enabled);
            $this->assertSame(2, $template->edit_version);
            $audit = AuditEvent::query()
                ->where('action', 'company.recurring_template.automatic_email_updated')
                ->sole();
            $this->assertSame(['automatic_email_enabled' => false], $audit->before);
            $this->assertSame([
                'edit_version' => 2,
                'automatic_email_enabled' => true,
            ], $audit->after);
        });
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /** @return array{User, User, Company, RecurringTemplate} */
    private function records(): array
    {
        CarbonImmutable::setTestNow('2026-08-29 10:00:00 UTC');
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle(
            $account,
            $owner,
            'Recurring Operations SRL',
        );
        $company->memberships()->create([
            'user_id' => $member->id,
            'role' => CompanyRole::Member,
        ]);
        $failed = $this->tenant($company, function (): RecurringTemplate {
            $customer = Customer::query()->create([
                'type' => 'COMPANY', 'legal_name' => 'Operations Customer SRL',
            ]);
            $failed = RecurringTemplate::query()->create([
                'client_creation_key' => (string) Str::uuid7(),
                'internal_name' => 'Failed billing', 'customer_id' => $customer->id,
            ]);
            $failed->update([
                'state' => 'ACTIVE', 'recurrence_kind' => 'MONTHLY',
                'start_date' => '2026-08-29', 'next_occurrence_date' => '2026-08-29',
                'schedule_timezone' => 'UTC', 'schedule_local_time' => '09:00',
                'next_run_at' => '2026-08-29 09:00:00+00', 'activated_at' => now(),
                'last_run_started_at' => now(), 'last_run_completed_at' => now(),
                'last_run_outcome' => 'FAILED', 'last_failure_category' => 'source_unavailable',
            ]);
            RecurringTemplate::query()->create([
                'client_creation_key' => (string) Str::uuid7(),
                'internal_name' => 'Healthy draft', 'customer_id' => $customer->id,
            ]);

            return $failed;
        });

        return [$owner, $member, $company, $failed];
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
}
