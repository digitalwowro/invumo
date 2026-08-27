<?php

namespace Tests\Feature\Modules\Delivery;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Models\CompanyEmailTemplate;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CompanyEmailTemplateHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_owner_sees_localized_system_defaults_for_every_event_and_language(): void
    {
        [$owner, $company] = $this->company();

        $this->actingAs($owner)
            ->get(route('company-email-templates.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->component('companies/settings/email-templates')
                ->has('templates', 8)
                ->where('templates.0.eventType', 'QUOTE_SENT')
                ->where('templates.0.languageCode', 'en')
                ->where('templates.0.override', false)
                ->where('templates.0.subject', 'Quote {{document_number}} from {{company_name}}')
                ->where('templates.1.languageCode', 'ro')
                ->where('templates.1.subject', 'Oferta {{document_number}} de la {{company_name}}')
                ->where('companySettingsNavigation.2.key', 'email_templates')
                ->where('limits.subject', 500)
                ->where('limits.body', 20000)
                ->where('limits.buttonLabel', 80)
                ->where('limits.signature', 5000));

        $owner->update(['language_code' => 'ro']);
        $this->get(route('company-email-templates.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('translations.settings.email_templates.title', 'Șabloane de email')
                ->where('eventOptions.0.label', 'Ofertă trimisă'));
    }

    public function test_preview_is_side_effect_free_and_save_reset_audit_only_safe_metadata(): void
    {
        [$owner, $company] = $this->company();
        $payload = $this->validTemplate([
            'subject' => 'Private subject {{document_number}}',
            'body' => 'Secret content for {{customer_name}}: {{document_total}}',
            'signature' => 'Private signature from {{company_name}}',
        ]);

        $this->actingAs($owner)
            ->postJson(route('company-email-templates.preview', $company), $payload)
            ->assertOk()
            ->assertJsonPath('subject', 'Private subject INV-2026-0042')
            ->assertJsonPath('body', 'Secret content for Ana Popescu: 1,234.56 RON')
            ->assertJsonPath('buttonUrl', 'https://app.invumo.com/i/example');
        $this->tenant($company, function (): void {
            $this->assertSame(0, CompanyEmailTemplate::query()->count());
            $this->assertSame(
                0,
                AuditEvent::query()->where('action', 'company.email_template.saved')->count(),
            );
        });

        $this->put(route('company-email-templates.update', $company), $payload)
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->tenant($company, function () use ($payload): void {
            $template = CompanyEmailTemplate::query()->sole();
            $audit = AuditEvent::query()->where('action', 'company.email_template.saved')->sole();
            $serialized = json_encode([$audit->before, $audit->after], JSON_THROW_ON_ERROR);

            $this->assertSame($payload['body'], $template->body);
            $this->assertSame('QUOTE_SENT', $audit->after['event_type']);
            $this->assertSame('en', $audit->after['language_code']);
            $this->assertTrue($audit->after['override']);
            $this->assertStringNotContainsString('Private subject', $serialized);
            $this->assertStringNotContainsString('Secret content', $serialized);
            $this->assertStringNotContainsString('Private signature', $serialized);
        });

        $this->delete(route('company-email-templates.destroy', [$company, 'QUOTE_SENT', 'en']))
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->tenant($company, function (): void {
            $this->assertSame(0, CompanyEmailTemplate::query()->count());
            $audit = AuditEvent::query()->where('action', 'company.email_template.reset')->sole();
            $this->assertSame(false, $audit->after['override']);
        });
    }

    public function test_validation_rejects_unknown_malformed_or_wrong_event_placeholders_and_bounds(): void
    {
        [$owner, $company] = $this->company();
        $this->actingAs($owner);

        foreach ([
            ['body' => 'Unknown {{not_supported}}'],
            ['body' => 'Malformed {customer_name}'],
            ['body' => 'Paid {{payment_amount}}'],
            ['subject' => "Line one\nLine two"],
            ['button_label' => str_repeat('x', 81)],
            ['signature' => str_repeat('x', 5001)],
        ] as $invalid) {
            $this->put(
                route('company-email-templates.update', $company),
                $this->validTemplate($invalid),
            )->assertSessionHasErrors(array_key_first($invalid));
        }

        $this->put(
            route('company-email-templates.update', $company),
            $this->validTemplate(['language_code' => 'de']),
        )->assertSessionHasErrors('language_code');
        $this->tenant(
            $company,
            fn () => $this->assertSame(0, CompanyEmailTemplate::query()->count()),
        );
    }

    public function test_admin_is_allowed_while_member_and_cross_company_access_are_denied(): void
    {
        [$owner, $company] = $this->company();
        [$outsider, $otherCompany] = $this->company('Other Company SRL');
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $member = User::factory()->create(['email' => 'member@example.com']);
        $company->memberships()->create(['user_id' => $admin->id, 'role' => CompanyRole::Admin]);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);

        $this->actingAs($admin)
            ->put(route('company-email-templates.update', $company), $this->validTemplate())
            ->assertRedirect();
        $this->actingAs($member)
            ->get(route('company-email-templates.index', $company))
            ->assertForbidden();
        $this->put(route('company-email-templates.update', $company), $this->validTemplate())
            ->assertForbidden();
        $this->actingAs($owner)
            ->get(route('company-email-templates.index', $otherCompany))
            ->assertNotFound();
        $this->actingAs($outsider)
            ->get(route('company-email-templates.index', $company))
            ->assertNotFound();
    }

    public function test_table_is_forced_rls_and_database_constraints_reject_invalid_rows(): void
    {
        [$owner, $company] = $this->company();
        $this->actingAs($owner)
            ->put(route('company-email-templates.update', $company), $this->validTemplate())
            ->assertRedirect();

        $this->assertSame(
            0,
            DB::connection('pgsql_schema')->table('company_email_templates')->count(),
        );
        $rls = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
            SELECT relrowsecurity, relforcerowsecurity
            FROM pg_class
            WHERE oid = 'public.company_email_templates'::regclass
            SQL);
        $this->assertTrue($rls->relrowsecurity);
        $this->assertTrue($rls->relforcerowsecurity);

        try {
            $this->tenant($company, fn () => DB::connection('pgsql')->table(
                'company_email_templates',
            )->insert([
                'id' => (string) Str::uuid7(),
                'company_id' => $company->id,
                'event_type' => 'UNKNOWN',
                'language_code' => 'en',
                'subject' => 'Subject',
                'body' => 'Body',
                'button_label' => 'View',
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $this->fail('The database accepted an invalid email event.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    /** @return array<string, mixed> */
    private function validTemplate(array $overrides = []): array
    {
        return array_replace([
            'event_type' => 'QUOTE_SENT',
            'language_code' => 'en',
            'subject' => 'Quote {{document_number}} from {{company_name}}',
            'body' => 'Hello {{customer_name}}, view {{document_total}} before {{valid_until}}.',
            'button_label' => 'View quote',
            'signature' => 'Regards, {{company_name}}',
        ], $overrides);
    }

    /** @return array{User, Company} */
    private function company(string $name = 'Email Templates SRL'): array
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);

        return [$owner, app(CreateCompany::class)->handle($account, $owner, $name)];
    }

    private function tenant(Company $company, callable $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
