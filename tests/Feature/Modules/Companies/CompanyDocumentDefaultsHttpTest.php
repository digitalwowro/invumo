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
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CompanyDocumentDefaultsHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_owner_sees_safe_initial_defaults_and_localized_options(): void
    {
        $owner = $this->accountOwner();
        $company = $this->companyFor($owner);

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            $settings = CompanySetting::query()->firstOrFail();

            $this->assertNull($settings->default_document_language);
            $this->assertNull($settings->default_payment_term_days);
            $this->assertSame(30, $settings->default_quote_validity_days);
            $this->assertNull($settings->default_terms_and_conditions);
        });

        $this->actingAs($owner)
            ->get(route('company-document-defaults.edit', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->component('companies/settings/documents')
                ->where('documentDefaults.documentLanguage', null)
                ->where('documentDefaults.paymentTermDays', null)
                ->where('documentDefaults.quoteValidityDays', '30')
                ->where('companySettingsNavigation.1.key', 'documents')
                ->where('languageOptions.0.value', 'en')
                ->where('languageOptions.0.label', 'English')
                ->where('languageOptions.1.value', 'ro'));

        $owner->update(['language_code' => 'ro']);

        $this->get(route('company-document-defaults.edit', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('translations.settings.layout.navigation.documents', 'Valori implicite pentru documente')
                ->where('translations.settings.documents.language_options.ro', 'Română')
                ->where('languageOptions.0.label', 'Engleză'));
    }

    public function test_owner_saves_defaults_with_privacy_bounded_audit_history(): void
    {
        $owner = $this->accountOwner();
        $company = $this->companyFor($owner);
        $payload = $this->validDefaults();

        $this->actingAs($owner)
            ->patch(route('company-document-defaults.update', $company), $payload)
            ->assertRedirect()
            ->assertSessionHas('status');

        app(TenantContext::class)->runAsSystem($company->id, function () use ($payload): void {
            $settings = CompanySetting::query()->firstOrFail();
            $event = AuditEvent::query()
                ->where('action', 'company.document_defaults.updated')
                ->firstOrFail();

            $this->assertSame('ro', $settings->default_document_language);
            $this->assertSame(14, $settings->default_payment_term_days);
            $this->assertSame(45, $settings->default_quote_validity_days);
            $this->assertSame($payload['default_terms_and_conditions'], $settings->default_terms_and_conditions);
            $this->assertSame($payload['default_quote_notes'], $settings->default_quote_notes);
            $this->assertSame($payload['default_invoice_notes'], $settings->default_invoice_notes);
            $this->assertSame('ro', $event->after['default_document_language']);
            $this->assertSame(14, $event->after['default_payment_term_days']);
            $this->assertSame(45, $event->after['default_quote_validity_days']);
            $this->assertEqualsCanonicalizing(
                [
                    'changed_fields',
                    'default_document_language',
                    'default_payment_term_days',
                    'default_quote_validity_days',
                ],
                array_keys($event->after ?? []),
            );
            $this->assertEqualsCanonicalizing(
                array_keys($event->before ?? []),
                array_keys($event->after ?? []),
            );
            $auditJson = json_encode([$event->before, $event->after], JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('Confidential terms', $auditJson);
            $this->assertStringNotContainsString('Private quote note', $auditJson);
            $this->assertStringNotContainsString('Private invoice note', $auditJson);
            $this->assertContains('default_terms_and_conditions', $event->after['changed_fields']);
            $this->assertContains('default_quote_notes', $event->after['changed_fields']);
            $this->assertContains('default_invoice_notes', $event->after['changed_fields']);
        });

        $this->patch(route('company-document-defaults.update', $company), $payload)
            ->assertRedirect();

        app(TenantContext::class)->runAsSystem(
            $company->id,
            fn () => $this->assertSame(
                1,
                AuditEvent::query()->where('action', 'company.document_defaults.updated')->count(),
            ),
        );
    }

    public function test_admin_is_allowed_while_member_and_cross_company_access_are_denied(): void
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
            ->patch(route('company-document-defaults.update', $company), $this->validDefaults())
            ->assertRedirect();

        $this->actingAs($member)
            ->get(route('company-document-defaults.edit', $company))
            ->assertForbidden();
        $this->patch(
            route('company-document-defaults.update', $company),
            $this->validDefaults(),
        )->assertForbidden();

        $this->actingAs($owner)
            ->get(route('company-document-defaults.edit', $otherCompany))
            ->assertNotFound();
        $this->patch(
            route('company-document-defaults.update', $otherCompany),
            $this->validDefaults(),
        )->assertNotFound();
    }

    public function test_validation_uses_the_active_romanian_catalogue(): void
    {
        $owner = $this->accountOwner();
        $owner->update(['language_code' => 'ro']);
        $company = $this->companyFor($owner);

        $response = $this->actingAs($owner)->patch(
            route('company-document-defaults.update', $company),
            $this->validDefaults([
                'default_document_language' => 'de',
                'default_payment_term_days' => '-1',
                'default_quote_validity_days' => '1.5',
                'default_terms_and_conditions' => [],
            ]),
        );

        $response->assertSessionHasErrors([
            'default_document_language',
            'default_payment_term_days',
            'default_quote_validity_days',
            'default_terms_and_conditions',
        ]);
        $this->assertStringContainsString(
            'Valoarea selectată',
            $response->getSession()->get('errors')->first('default_document_language'),
        );
    }

    /** @return array<string, mixed> */
    private function validDefaults(array $overrides = []): array
    {
        return array_replace([
            'default_document_language' => 'ro',
            'default_payment_term_days' => '14',
            'default_quote_validity_days' => '45',
            'default_terms_and_conditions' => "Confidential terms\nSecond line",
            'default_quote_notes' => 'Private quote note',
            'default_invoice_notes' => 'Private invoice note',
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
