<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Models\NumberSeries;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CompanyNumberSeriesHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_owner_sees_default_series_and_company_local_preview(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-31 12:30:00 UTC'));
        $owner = $this->accountOwner();
        $company = $this->companyFor($owner);
        $this->setTimezone($company, 'Pacific/Kiritimati');

        $this->actingAs($owner)
            ->get(route('company-number-series.edit', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->component('companies/settings/numbering')
                ->where('numberSeries.quote.pattern', 'Q-{YEAR}-{NUMBER}')
                ->where('numberSeries.quote.padding', '4')
                ->where('numberSeries.quote.resetPolicy', 'NEVER')
                ->where('numberSeries.quote.preview', 'Q-2027-0001')
                ->where('numberSeries.invoice.pattern', 'I-{YEAR}-{NUMBER}')
                ->where('numberSeries.invoice.preview', 'I-2027-0001')
                ->where('previewContext.year', 2027)
                ->where('numberSeriesLimits.patternCharacters', 120)
                ->where('numberSeriesLimits.minimumPadding', 1)
                ->where('numberSeriesLimits.maximumPadding', 12)
                ->where('resetPolicyOptions.0.value', 'NEVER')
                ->where('resetPolicyOptions.0.label', 'Never reset')
                ->where('companySettingsNavigation.3.key', 'numbering'));

        $owner->update(['language_code' => 'ro']);

        $this->get(route('company-number-series.edit', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('translations.settings.layout.navigation.numbering', 'Numerotare')
                ->where('translations.settings.numbering.title', 'Numerotarea documentelor')
                ->where('resetPolicyOptions.1.label', 'Se resetează anual'));
    }

    public function test_owner_saves_custom_series_with_versioned_privacy_safe_audit(): void
    {
        $owner = $this->accountOwner();
        $company = $this->companyFor($owner);
        $this->setTimezone($company, 'Europe/Bucharest');
        $payload = $this->configuration([
            'quote' => [
                'pattern' => 'O-{YEAR}-{NUMBER}-FINAL',
                'padding' => '6',
                'reset_policy' => 'ANNUAL',
            ],
            'invoice' => [
                'pattern' => 'INV-{NUMBER}',
                'padding' => '5',
                'reset_policy' => 'NEVER',
            ],
        ]);

        $this->actingAs($owner)
            ->patch(route('company-number-series.update', $company), $payload)
            ->assertRedirect()
            ->assertSessionHas('status');

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            $active = NumberSeries::query()
                ->whereNull('retired_at')
                ->get()
                ->keyBy(fn (NumberSeries $series): string => $series->document_type->value);
            $events = AuditEvent::query()
                ->where('action', 'company.number_series.updated')
                ->get();

            $this->assertSame(4, NumberSeries::query()->count());
            $this->assertSame(2, NumberSeries::query()->whereNotNull('retired_at')->count());
            $this->assertSame('O-{YEAR}-{NUMBER}-FINAL', $active['QUOTE']->format_pattern);
            $this->assertSame(6, $active['QUOTE']->padding);
            $this->assertSame('ANNUAL', $active['QUOTE']->reset_policy->value);
            $this->assertSame('INV-{NUMBER}', $active['INVOICE']->format_pattern);
            $this->assertCount(2, $events);

            foreach ($events as $event) {
                $this->assertEqualsCanonicalizing(
                    ['document_type', 'changed_fields', 'padding', 'reset_policy'],
                    array_keys($event->after ?? []),
                );
            }

            $audit = json_encode(
                $events->map(fn (AuditEvent $event): array => [$event->before, $event->after])->all(),
                JSON_THROW_ON_ERROR,
            );
            $this->assertStringNotContainsString('O-{YEAR}-{NUMBER}-FINAL', $audit);
            $this->assertStringNotContainsString('INV-{NUMBER}', $audit);
            $this->assertContains('format_pattern', $events[0]->after['changed_fields']);
        });

        $this->patch(route('company-number-series.update', $company), $payload)
            ->assertRedirect();

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            $this->assertSame(4, NumberSeries::query()->count());
            $this->assertSame(
                2,
                AuditEvent::query()->where('action', 'company.number_series.updated')->count(),
            );
        });
    }

    public function test_admin_is_allowed_while_member_and_cross_company_access_are_denied(): void
    {
        $owner = $this->accountOwner();
        $admin = $this->accountOwner('admin@example.com');
        $member = $this->accountOwner('member@example.com');
        $outsider = $this->accountOwner('outsider@example.com');
        $company = $this->companyFor($owner);
        $otherCompany = $this->companyFor($outsider, 'Other SRL');
        $this->setTimezone($company, 'Europe/Bucharest');
        $this->addMember($company, $admin, CompanyRole::Admin);
        $this->addMember($company, $member, CompanyRole::Member);

        $this->actingAs($admin)
            ->patch(route('company-number-series.update', $company), $this->configuration())
            ->assertRedirect();

        $this->actingAs($member)
            ->get(route('company-number-series.edit', $company))
            ->assertForbidden();
        $this->patch(route('company-number-series.update', $company), $this->configuration())
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('company-number-series.edit', $otherCompany))
            ->assertNotFound();
        $this->patch(route('company-number-series.update', $otherCompany), $this->configuration())
            ->assertNotFound();
    }

    public function test_validation_rejects_invalid_tokens_padding_and_reset_policy_in_romanian(): void
    {
        $owner = $this->accountOwner();
        $owner->update(['language_code' => 'ro']);
        $company = $this->companyFor($owner);

        $response = $this->actingAs($owner)->patch(
            route('company-number-series.update', $company),
            $this->configuration([
                'quote' => [
                    'pattern' => 'Q-{YEAR}',
                    'padding' => '13',
                    'reset_policy' => 'MONTHLY',
                ],
                'invoice' => [
                    'pattern' => 'I-{YEAR}-{YEAR}-{NUMBER}',
                    'padding' => '0',
                    'reset_policy' => 'NEVER',
                ],
            ]),
        );

        $response->assertSessionHasErrors([
            'quote.pattern',
            'quote.padding',
            'quote.reset_policy',
            'invoice.pattern',
            'invoice.padding',
        ]);
        $this->assertStringContainsString(
            'Folosește {NUMBER} exact o dată',
            $response->getSession()->get('errors')->first('quote.pattern'),
        );

        $this->actingAs($owner)->patch(
            route('company-number-series.update', $company),
            $this->configuration(['invoice' => [
                'pattern' => 'INV-{NUMBER}',
                'reset_policy' => 'ANNUAL',
            ]]),
        )->assertSessionHasErrors([
            'invoice.pattern' => 'Resetarea anuală necesită {YEAR} în modelul numărului.',
        ]);
    }

    public function test_year_and_annual_changes_require_company_timezone(): void
    {
        $owner = $this->accountOwner();
        $company = $this->companyFor($owner);

        $this->actingAs($owner)
            ->patch(
                route('company-number-series.update', $company),
                $this->configuration(['quote' => ['padding' => '5']]),
            )
            ->assertSessionHasErrors('quote.pattern');

        $withoutYear = $this->configuration(['quote' => [
            'pattern' => 'Q-{NUMBER}',
            'padding' => '5',
        ]]);
        $this->patch(route('company-number-series.update', $company), $withoutYear)
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->patch(
            route('company-number-series.update', $company),
            $this->configuration(['quote' => [
                'pattern' => 'Q-{NUMBER}',
                'padding' => '5',
                'reset_policy' => 'ANNUAL',
            ]]),
        )->assertSessionHasErrors([
            'quote.pattern' => 'Annual reset requires {YEAR} in the number pattern.',
        ]);

        $this->patch(
            route('company-number-series.update', $company),
            $this->configuration(['quote' => [
                'padding' => '5',
                'reset_policy' => 'ANNUAL',
            ]]),
        )->assertSessionHasErrors('quote.pattern');
    }

    /** @return array<string, mixed> */
    private function configuration(array $overrides = []): array
    {
        $defaults = [
            'quote' => [
                'pattern' => 'Q-{YEAR}-{NUMBER}',
                'padding' => '4',
                'reset_policy' => 'NEVER',
            ],
            'invoice' => [
                'pattern' => 'I-{YEAR}-{NUMBER}',
                'padding' => '4',
                'reset_policy' => 'NEVER',
            ],
        ];

        foreach ($overrides as $key => $values) {
            $defaults[$key] = array_replace($defaults[$key], $values);
        }

        return $defaults;
    }

    private function accountOwner(string $email = 'owner@example.com'): User
    {
        return User::factory()->create(['email' => $email]);
    }

    private function companyFor(User $owner, string $name = 'Acme SRL'): Company
    {
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => $plan->id,
        ]);

        return app(CreateCompany::class)->handle($account, $owner, $name);
    }

    private function setTimezone(Company $company, string $timezone): void
    {
        app(TenantContext::class)->runAsSystem(
            $company->id,
            fn () => CompanySetting::query()->firstOrFail()->update(['timezone' => $timezone]),
        );
    }

    private function addMember(Company $company, User $user, CompanyRole $role): void
    {
        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }
}
