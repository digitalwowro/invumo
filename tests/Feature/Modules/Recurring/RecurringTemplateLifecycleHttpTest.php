<?php

namespace Tests\Feature\Modules\Recurring;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Recurring\Data\RecurringTemplateState;
use App\Modules\Recurring\Models\RecurringTemplate;
use App\Modules\Recurring\Models\RecurringTemplateLine;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RecurringTemplateLifecycleHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_schedule_lifecycle_and_terminal_duplication_follow_role_and_calendar_rules(): void
    {
        CarbonImmutable::setTestNow('2026-03-01 00:00:00 UTC');

        try {
            $owner = User::factory()->create();
            $member = User::factory()->create();
            $company = $this->company($owner);
            $company->memberships()->create([
                'user_id' => $member->id, 'role' => CompanyRole::Member,
            ]);
            $template = $this->template($company);
            $schedule = [
                'edit_version' => 1,
                'recurrence_kind' => 'MONTHLY',
                'custom_interval_count' => null,
                'custom_interval_unit' => null,
                'start_date' => '2026-01-31',
                'end_date' => null,
                'maximum_occurrence_count' => 12,
            ];

            $this->actingAs($member)
                ->patch(route('recurring.schedule.update', [$company, $template]), $schedule)
                ->assertRedirect()->assertSessionDoesntHaveErrors();
            $this->post(route('recurring.transition', [$company, $template, 'activate']), [
                'edit_version' => 2, 'confirmed' => true,
            ])->assertForbidden();

            $incompleteLine = $this->tenant($company, fn (): RecurringTemplateLine => RecurringTemplateLine::query()->create([
                'recurring_template_id' => $template->id, 'position' => 2,
                'period_unit' => 'NONE', 'discount_percentage' => '0', 'tax_percentage' => '0',
            ]));
            $this->actingAs($owner)
                ->post(route('recurring.transition', [$company, $template, 'activate']), [
                    'edit_version' => 2, 'confirmed' => true,
                ])->assertRedirect()->assertSessionHasErrors('transition');
            $this->tenant($company, fn (): bool => $incompleteLine->delete());
            $incompletePeriodLine = $this->tenant(
                $company,
                fn (): RecurringTemplateLine => RecurringTemplateLine::query()->create([
                    'recurring_template_id' => $template->id, 'position' => 2,
                    'description' => 'Annual service', 'item_price' => '100', 'quantity' => '1',
                    'period_unit' => 'YEAR', 'period_quantity' => null,
                    'discount_percentage' => '0', 'tax_percentage' => '0',
                ]),
            );
            $this->post(route('recurring.transition', [$company, $template, 'activate']), [
                'edit_version' => 2, 'confirmed' => true,
            ])->assertRedirect()->assertSessionHasErrors('transition');
            $this->tenant($company, fn (): bool => $incompletePeriodLine->delete());
            $this->post(route('recurring.transition', [$company, $template, 'activate']), [
                'edit_version' => 2, 'confirmed' => true,
            ])->assertRedirect()->assertSessionDoesntHaveErrors();
            $this->assertTemplate($company, RecurringTemplateState::Active, 3, '2026-03-31');

            $this->post(route('recurring.transition', [$company, $template, 'pause']), [
                'edit_version' => 3, 'confirmed' => true,
            ])->assertRedirect();
            $this->assertTemplate($company, RecurringTemplateState::Paused, 4, null);

            CarbonImmutable::setTestNow('2026-04-01 00:00:00 UTC');
            $this->post(route('recurring.transition', [$company, $template, 'resume']), [
                'edit_version' => 4, 'confirmed' => true,
            ])->assertRedirect();
            $this->assertTemplate($company, RecurringTemplateState::Active, 5, '2026-04-30');

            $this->post(route('recurring.transition', [$company, $template, 'complete']), [
                'edit_version' => 5, 'confirmed' => true,
            ])->assertRedirect();
            $this->assertTemplate($company, RecurringTemplateState::Completed, 6, null);

            try {
                $this->tenant($company, fn (): bool => RecurringTemplate::query()
                    ->whereKey($template->id)->update(['internal_name' => 'Changed after completion']));
                $this->fail('A Completed recurring template must remain immutable.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->errorInfo[0]);
            }

            $creationKey = (string) Str::uuid7();
            $this->actingAs($member)
                ->post(route('recurring.duplicate', [$company, $template]), [
                    'creation_key' => $creationKey,
                ])->assertRedirect();
            $this->tenant($company, function () use ($creationKey): void {
                $copy = RecurringTemplate::query()
                    ->where('client_creation_key', $creationKey)->sole();
                $this->assertSame(RecurringTemplateState::Draft, $copy->state);
                $this->assertSame('2026-01-31', $copy->start_date?->toDateString());
                $this->assertNull($copy->next_run_at);
                $this->assertSame(1, RecurringTemplateLine::query()
                    ->where('recurring_template_id', $copy->id)->count());
            });
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_company_schedule_change_recalculates_active_next_run_atomically(): void
    {
        CarbonImmutable::setTestNow('2026-03-01 00:00:00 UTC');

        try {
            $owner = User::factory()->create();
            $company = $this->company($owner);
            $template = $this->template($company);
            $this->actingAs($owner)->patch(
                route('recurring.schedule.update', [$company, $template]),
                [
                    'edit_version' => 1, 'recurrence_kind' => 'MONTHLY',
                    'start_date' => '2026-01-31', 'end_date' => null,
                    'maximum_occurrence_count' => null,
                ],
            )->assertRedirect();
            $this->post(route('recurring.transition', [$company, $template, 'activate']), [
                'edit_version' => 2, 'confirmed' => true,
            ])->assertRedirect();

            $this->patch(route('company-settings.profile.update', $company), [
                ...$this->configuration(),
                'timezone' => 'UTC',
                'automation_local_time' => '10:00',
                'confirm_schedule_change' => true,
            ])->assertRedirect()->assertSessionDoesntHaveErrors();

            $this->tenant($company, function (): void {
                $template = RecurringTemplate::query()->sole();
                $this->assertSame('2026-03-31', $template->next_occurrence_date?->toDateString());
                $this->assertSame('UTC', $template->schedule_timezone);
                $this->assertSame('2026-03-31 10:00:00', $template->next_run_at?->format('Y-m-d H:i:s'));
            });
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_active_schedule_edit_reanchors_dates_at_the_monotonic_cursor(): void
    {
        CarbonImmutable::setTestNow('2026-03-01 00:00:00 UTC');

        try {
            $owner = User::factory()->create();
            $company = $this->company($owner);
            $template = $this->template($company);
            $this->actingAs($owner)->patch(
                route('recurring.schedule.update', [$company, $template]),
                [
                    'edit_version' => 1, 'recurrence_kind' => 'MONTHLY',
                    'start_date' => '2026-01-31', 'end_date' => null,
                    'maximum_occurrence_count' => null,
                ],
            )->assertRedirect();
            $this->post(route('recurring.transition', [$company, $template, 'activate']), [
                'edit_version' => 2, 'confirmed' => true,
            ])->assertRedirect();
            $this->patch(route('recurring.schedule.update', [$company, $template]), [
                'edit_version' => 3, 'recurrence_kind' => 'WEEKLY',
                'start_date' => '2026-03-01', 'end_date' => null,
                'maximum_occurrence_count' => null, 'confirmed' => true,
            ])->assertRedirect()->assertSessionDoesntHaveErrors();

            $this->tenant($company, function (): void {
                $template = RecurringTemplate::query()->sole();
                $this->assertSame(2, $template->schedule_anchor_ordinal);
                $this->assertSame(2, $template->next_logical_ordinal);
                $this->assertSame('2026-03-01', $template->next_occurrence_date?->toDateString());
            });
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    private function company(User $owner): Company
    {
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Schedule Test SRL');
        $this->tenant($company, function (): void {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'Europe/Bucharest', 'automation_local_time' => '09:00',
            ]);
            CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]);
        });

        return $company;
    }

    private function template(Company $company): RecurringTemplate
    {
        return $this->tenant($company, function (): RecurringTemplate {
            $customer = Customer::query()->create([
                'type' => 'COMPANY', 'legal_name' => 'Schedule Customer SRL',
            ]);
            $template = RecurringTemplate::query()->create([
                'client_creation_key' => (string) Str::uuid7(),
                'internal_name' => 'Monthly service', 'customer_id' => $customer->id,
            ]);
            RecurringTemplateLine::query()->create([
                'recurring_template_id' => $template->id, 'position' => 1,
                'description' => 'Service', 'item_price' => '100', 'quantity' => '1',
                'unit' => 'month', 'discount_percentage' => '0',
                'tax_name' => null, 'tax_percentage' => '0',
            ]);

            return $template;
        });
    }

    /** @return array<string, mixed> */
    private function configuration(): array
    {
        return [
            'display_name' => 'Schedule Test SRL', 'legal_name' => 'Schedule Test SRL',
            'trading_name' => null, 'address_line_1' => null, 'address_line_2' => null,
            'city' => null, 'region' => null, 'postal_code' => null, 'country_code' => null,
            'tax_registration_label' => null, 'tax_registration_identifier' => null,
            'business_registration_label' => null, 'business_registration_number' => null,
            'email' => null, 'phone' => null, 'website' => null,
            'timezone' => 'Europe/Bucharest', 'automation_local_time' => '09:00',
            'currency_code' => 'RON', 'currency_precision' => 2,
            'currency_display_style' => 'CODE', 'confirm_schedule_change' => false,
        ];
    }

    private function assertTemplate(
        Company $company,
        RecurringTemplateState $state,
        int $version,
        ?string $nextDate,
    ): void {
        $this->tenant($company, function () use ($state, $version, $nextDate): void {
            $template = RecurringTemplate::query()->where('internal_name', 'Monthly service')->sole();
            $this->assertSame($state, $template->state);
            $this->assertSame($version, $template->edit_version);
            $this->assertSame($nextDate, $template->next_occurrence_date?->toDateString());
        });
    }

    /** @template TReturn @param Closure(): TReturn $callback @return TReturn */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
