<?php

namespace Tests\Feature\Modules\Audit;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CompanyAuditHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_owner_and_admin_receive_localized_privacy_safe_history_while_member_is_denied(): void
    {
        [$company, $owner] = $this->company('Audit SRL');
        $admin = User::factory()->create([
            'name' => 'Audit Admin',
            'email' => 'private-admin@example.com',
        ]);
        $member = User::factory()->create();
        $company->memberships()->create(['user_id' => $admin->id, 'role' => CompanyRole::Admin]);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $this->event($company, [
            'actor_type' => AuditActorType::User,
            'actor_user_id' => $admin->id,
            'impersonator_user_id' => $owner->id,
            'action' => 'company.customer.updated',
            'target_type' => 'Customer',
            'reason' => 'Approved correction',
            'before' => ['status' => 'DRAFT'],
            'after' => ['status' => 'ACTIVE'],
            'correlation_id' => 'hidden-correlation-marker',
            'idempotency_reference' => 'hidden-idempotency-marker',
        ]);
        $count = $this->tenant($company, fn (): int => AuditEvent::query()->count());

        foreach ([$owner, $admin] as $actor) {
            $response = $this->actingAs($actor)->get(route('company-audit.index', $company));
            $response->assertInertia(fn (Assert $page) => $page
                ->component('companies/settings/audit')
                ->where('companyContext.abilities.view_audit', true)
                ->where('companySettingsNavigation.9.key', 'audit')
                ->where('audit.items.0.action', 'company.customer.updated')
                ->where('audit.items.0.actorName', 'Audit Admin')
                ->where('audit.items.0.impersonatorName', $owner->name)
                ->where('audit.items.0.reason', 'Approved correction')
                ->where('audit.items.0.before.status', 'DRAFT')
                ->where('audit.items.0.after.status', 'ACTIVE')
                ->missing('audit.items.0.correlationId')
                ->missing('audit.items.0.idempotencyReference'));
            $response->assertDontSee('hidden-correlation-marker')
                ->assertDontSee('hidden-idempotency-marker');
            if ($actor->is($owner)) {
                $response->assertDontSee('private-admin@example.com');
            }
        }

        $this->actingAs($member)->get(route('company-audit.index', $company))->assertForbidden();
        $this->get(route('company-members.index', $company))->assertInertia(
            fn (Assert $page) => $page
                ->has('companySettingsNavigation', 1)
                ->where('companySettingsNavigation.0.key', 'members')
                ->where('companyContext.abilities.view_audit', false),
        );
        $this->assertSame($count, $this->tenant($company, fn (): int => AuditEvent::query()->count()));

        $owner->update(['language_code' => 'ro']);
        $this->actingAs($owner)->get(route('company-audit.index', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('translations.settings.audit.title', 'Istoric de audit')
                ->where(
                    'translations.settings.audit.actions',
                    fn (mixed $actions): bool => $actions['company.customer.updated'] === 'Client actualizat',
                ));
    }

    public function test_search_and_filters_are_literal_bounded_and_use_company_local_dates(): void
    {
        [$company, $owner] = $this->company('Filters SRL');
        $this->tenant($company, fn () => CompanySetting::query()->firstOrFail()
            ->update(['timezone' => 'Europe/Bucharest']));
        $this->event($company, [
            'actor_type' => AuditActorType::System,
            'actor_reference' => 'scheduler',
            'action' => 'company.recurring_template.occurrence_failed',
            'target_type' => 'RecurringTemplate',
            'reason' => 'REF_50%',
            'occurred_at' => '2026-08-28 21:30:00+00',
        ]);
        $this->event($company, [
            'actor_type' => AuditActorType::User,
            'actor_user_id' => $owner->id,
            'action' => 'company.customer.updated',
            'target_type' => 'Customer',
            'reason' => 'REFX500',
            'occurred_at' => '2026-08-29 21:30:00+00',
        ]);

        $this->actingAs($owner)->get(route('company-audit.index', [
            $company,
            'q' => 'REF_50%',
            'actor_type' => 'SYSTEM',
            'target_type' => 'RecurringTemplate',
            'date_from' => '2026-08-29',
            'date_to' => '2026-08-29',
            'sort' => 'oldest',
        ]))->assertInertia(fn (Assert $page) => $page
            ->has('audit.items', 1)
            ->where('audit.items.0.reason', 'REF_50%')
            ->where('filters.actorType', 'SYSTEM')
            ->where('filters.targetType', 'RecurringTemplate')
            ->where('timezone', 'Europe/Bucharest'));

        $this->get(route('company-audit.index', [$company, 'q' => str_repeat('x', 121)]))
            ->assertSessionHasErrors('q');
    }

    public function test_cursor_and_database_indexes_are_stable_and_rls_hides_foreign_events(): void
    {
        [$company, $owner] = $this->company('Pagination SRL');
        [$other, $otherOwner] = $this->company('Foreign SRL');
        $foreign = $this->event($other, [
            'actor_user_id' => $otherOwner->id,
            'action' => 'company.customer.updated',
            'target_type' => 'Customer',
            'reason' => 'FOREIGN-MARKER',
        ]);

        for ($index = 1; $index <= 26; $index++) {
            $this->event($company, [
                'actor_user_id' => $owner->id,
                'action' => 'company.customer.updated',
                'target_type' => 'Customer',
                'reason' => 'PAGE-'.$index,
                'occurred_at' => '2026-08-29 12:00:00+00',
            ]);
        }

        $response = $this->actingAs($owner)->get(route('company-audit.index', [
            $company, 'sort' => 'oldest', 'per_page' => 25,
        ]));
        $response->assertInertia(fn (Assert $page) => $page->has('audit.items', 25));
        $nextUrl = $response->inertiaProps('audit.nextUrl');
        $this->assertIsString($nextUrl);
        $firstIds = collect($response->inertiaProps('audit.items'))->pluck('id');
        $next = $this->get($nextUrl);
        $next->assertOk();
        $this->assertCount(0, $firstIds->intersect(
            collect($next->inertiaProps('audit.items'))->pluck('id'),
        ));
        $this->assertStringNotContainsString('FOREIGN-MARKER', $response->getContent());
        $this->assertNull($this->tenant($company, fn () => AuditEvent::query()->find($foreign->id)));

        foreach ([
            'audit_events_company_actor_history_index' => '(company_id, actor_type, occurred_at, id)',
            'audit_events_company_target_history_index' => '(company_id, target_type, occurred_at, id)',
            'audit_events_company_search_trgm_index' => 'gin_trgm_ops',
        ] as $name => $fragment) {
            $definition = DB::connection('pgsql_schema')->table('pg_indexes')
                ->where('schemaname', 'public')->where('indexname', $name)->value('indexdef');
            $this->assertIsString($definition);
            $this->assertStringContainsString($fragment, $definition);
        }
    }

    /** @return array{Company, User} */
    private function company(string $name): array
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);

        return [app(CreateCompany::class)->handle($account, $owner, $name), $owner];
    }

    /** @param array<string, mixed> $overrides */
    private function event(Company $company, array $overrides): AuditEvent
    {
        return $this->tenant($company, fn (): AuditEvent => AuditEvent::query()->create([
            'actor_type' => AuditActorType::User,
            'action' => 'company.updated',
            'target_type' => 'Company',
            'target_id' => $company->id,
            'occurred_at' => now(),
            ...$overrides,
        ]));
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
