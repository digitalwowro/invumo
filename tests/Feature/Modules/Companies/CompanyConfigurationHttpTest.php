<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CompanyConfigurationHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_company_creation_provides_safe_initial_settings_and_localized_page_props(): void
    {
        $owner = $this->accountOwner();
        $company = $this->companyFor($owner);

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            $settings = CompanySetting::query()->firstOrFail();

            $this->assertSame('Acme SRL', $settings->legal_name);
            $this->assertSame('09:00:00', $settings->automation_local_time);
            $this->assertNull($settings->timezone);
            $this->assertNull($settings->currency_display_style);
            $this->assertSame(0, CompanyCurrency::query()->count());
        });

        $this->actingAs($owner)
            ->get(route('company-settings.profile.edit', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->component('companies/settings/profile')
                ->where('configuration.legalName', 'Acme SRL')
                ->where('configuration.automationLocalTime', '09:00')
                ->where('configuration.timezone', null)
                ->where('configuration.currencyCode', null)
                ->where('companySettingsNavigation.0.key', 'profile')
                ->where('companySettingsNavigation.1.key', 'members')
                ->where('currencyOptions', fn (mixed $options) => collect($options)
                    ->contains('value', 'RON'))
                ->where('timezoneOptions', fn (mixed $options) => collect($options)
                    ->contains('value', 'Europe/Bucharest')));

        $owner->update(['language_code' => 'ro']);

        $this->get(route('company-settings.profile.edit', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('translations.settings.layout.title', 'Setările companiei')
                ->where('translations.settings.profile.fields.country_code', 'Țară'));
    }

    public function test_owner_saves_structured_configuration_with_bounded_audit_history(): void
    {
        $owner = $this->accountOwner();
        $company = $this->companyFor($owner);
        $payload = $this->validConfiguration();

        $this->actingAs($owner)
            ->patch(route('company-settings.profile.update', $company), $payload)
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame('Acme Workspace', $company->refresh()->name);

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            $settings = CompanySetting::query()->firstOrFail();
            $currency = CompanyCurrency::query()->where('is_default', true)->firstOrFail();
            $event = AuditEvent::query()
                ->where('action', 'company.configuration.updated')
                ->firstOrFail();

            $this->assertSame('Acme Legal SRL', $settings->legal_name);
            $this->assertSame('RO', $settings->country_code);
            $this->assertSame('Europe/Bucharest', $settings->timezone);
            $this->assertSame('RON', $currency->currency_code);
            $this->assertSame(2, $currency->currency_precision);
            $this->assertTrue($currency->is_default);
            $this->assertSame('Acme SRL', $event->before['display_name']);
            $this->assertSame('Acme Workspace', $event->after['display_name']);
            $this->assertSame('RON', $event->after['currency_code']);
            $this->assertArrayNotHasKey('confirm_schedule_change', $event->after);
            $this->assertEqualsCanonicalizing(
                array_keys($event->before ?? []),
                array_keys($event->after ?? []),
            );
        });

        $this->patch(route('company-settings.profile.update', $company), $payload)
            ->assertRedirect();

        app(TenantContext::class)->runAsSystem(
            $company->id,
            fn () => $this->assertSame(
                1,
                AuditEvent::query()->where('action', 'company.configuration.updated')->count(),
            ),
        );
    }

    public function test_admin_can_update_while_member_and_cross_company_access_are_denied(): void
    {
        $owner = $this->accountOwner();
        $admin = $this->accountOwner('admin@example.com');
        $member = $this->accountOwner('member@example.com');
        $outsider = $this->accountOwner('outsider@example.com');
        $company = $this->companyFor($owner);
        $otherCompany = $this->companyFor($outsider, 'Other SRL');
        $this->addMember($company, $admin, CompanyRole::Admin);
        $this->addMember($company, $member, CompanyRole::Member);

        $this->actingAs($admin)
            ->patch(route('company-settings.profile.update', $company), $this->validConfiguration())
            ->assertRedirect();

        $this->actingAs($member)
            ->get(route('company-settings.profile.edit', $company))
            ->assertForbidden();
        $this->patch(
            route('company-settings.profile.update', $company),
            $this->validConfiguration(['legal_name' => 'Forbidden SRL']),
        )->assertForbidden();
        $this->get(route('company-settings.index', $company))
            ->assertRedirect(route('company-members.index', $company));

        $this->actingAs($owner)
            ->get(route('company-settings.index', $company))
            ->assertRedirect(route('company-settings.profile.edit', $company));
        $this->get(route('company-settings.profile.edit', $otherCompany))
            ->assertNotFound();
    }

    public function test_schedule_changes_require_confirmation_and_rollback_without_it(): void
    {
        $owner = $this->accountOwner();
        $company = $this->companyFor($owner);
        $this->actingAs($owner)
            ->patch(route('company-settings.profile.update', $company), $this->validConfiguration())
            ->assertRedirect();

        $changed = $this->validConfiguration([
            'timezone' => 'UTC',
            'automation_local_time' => '08:30',
        ]);
        $this->patch(route('company-settings.profile.update', $company), $changed)
            ->assertSessionHasErrors('confirm_schedule_change');

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            $settings = CompanySetting::query()->firstOrFail();
            $this->assertSame('Europe/Bucharest', $settings->timezone);
            $this->assertSame('09:00:00', $settings->automation_local_time);
            $this->assertSame(
                1,
                AuditEvent::query()->where('action', 'company.configuration.updated')->count(),
            );
        });

        $this->patch(route('company-settings.profile.update', $company), [
            ...$changed,
            'confirm_schedule_change' => true,
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            $settings = CompanySetting::query()->firstOrFail();
            $this->assertSame('UTC', $settings->timezone);
            $this->assertSame('08:30:00', $settings->automation_local_time);
        });
    }

    public function test_switching_default_currency_preserves_the_previous_currency_record(): void
    {
        $owner = $this->accountOwner();
        $company = $this->companyFor($owner);
        $this->actingAs($owner)
            ->patch(route('company-settings.profile.update', $company), $this->validConfiguration())
            ->assertRedirect();

        $this->patch(
            route('company-settings.profile.update', $company),
            $this->validConfiguration([
                'currency_code' => 'EUR',
                'currency_precision' => '3',
            ]),
        )->assertRedirect();

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            $ron = CompanyCurrency::query()->where('currency_code', 'RON')->firstOrFail();
            $eur = CompanyCurrency::query()->where('currency_code', 'EUR')->firstOrFail();

            $this->assertFalse($ron->is_default);
            $this->assertTrue($ron->active);
            $this->assertTrue($eur->is_default);
            $this->assertSame(3, $eur->currency_precision);
        });
    }

    public function test_validation_uses_the_active_romanian_catalogue(): void
    {
        $owner = $this->accountOwner();
        $owner->update(['language_code' => 'ro']);
        $company = $this->companyFor($owner);

        $response = $this->actingAs($owner)->patch(
            route('company-settings.profile.update', $company),
            $this->validConfiguration([
                'legal_name' => '',
                'timezone' => 'Europe/Invalid',
                'currency_code' => 'BGN',
                'currency_precision' => '9',
                'currency_display_style' => 'INVALID',
                'tax_registration_identifier' => '',
            ]),
        );

        $response->assertSessionHasErrors([
            'legal_name', 'timezone', 'currency_code',
            'currency_precision', 'currency_display_style',
            'tax_registration_identifier',
        ]);
        $this->assertStringContainsString(
            'Valoarea selectată',
            $response->getSession()->get('errors')->first('timezone'),
        );
    }

    /** @return array<string, mixed> */
    private function validConfiguration(array $overrides = []): array
    {
        return array_replace([
            'display_name' => 'Acme Workspace',
            'legal_name' => 'Acme Legal SRL',
            'trading_name' => 'Acme',
            'address_line_1' => '10 Exemplu Street',
            'address_line_2' => 'Floor 2',
            'city' => 'Bucharest',
            'region' => 'Bucharest',
            'postal_code' => '010101',
            'country_code' => 'RO',
            'tax_registration_label' => 'VAT ID',
            'tax_registration_identifier' => 'RO12345678',
            'business_registration_label' => 'Trade Registry',
            'business_registration_number' => 'J40/123/2026',
            'email' => 'office@example.com',
            'phone' => '+40 21 000 0000',
            'website' => 'https://example.com',
            'timezone' => 'Europe/Bucharest',
            'automation_local_time' => '09:00',
            'currency_code' => 'RON',
            'currency_precision' => '2',
            'currency_display_style' => 'CODE',
        ], $overrides);
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
}
