<?php

namespace Tests\Feature\Modules\Platform;

use App\Models\User;
use App\Modules\Audit\Data\DataErasureAction;
use App\Modules\Audit\Models\DataErasureEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\CompanyErasureFile;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Platform\Data\PlatformRole;
use App\Modules\Platform\Models\PlatformOperator;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformOperationsHttpTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_platform_lists_expose_only_approved_control_plane_fields(): void
    {
        $operator = $this->platformOwner();
        $target = $this->accountOwner('target@example.com');
        $company = app(CreateCompany::class)->handle($target->account, $target, 'Target SRL');

        $this->actingAs($operator)
            ->get(route('platform.users.index', ['q' => $target->email]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/users')
                ->has('page.items', 1)
                ->where('page.items.0.email', $target->email)
                ->where('page.items.0.canImpersonate', true)
                ->missing('page.items.0.password')
                ->missing('page.items.0.remember_token')
                ->where('translations.users.title', 'Users'));

        $this->get(route('platform.users.index', ['q' => $operator->email]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('page.items', 1)
                ->where('page.items.0.email', $operator->email)
                ->where('page.items.0.canImpersonate', false));

        $this->get(route('platform.accounts.index', ['q' => $target->email]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/accounts')
                ->has('page.items', 1)
                ->where('page.items.0.ownerEmail', $target->email)
                ->missing('page.items.0.companies'));

        $this->get(route('platform.companies.index', ['q' => 'Target']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/companies')
                ->has('page.items', 1)
                ->where('page.items.0.id', $company->id)
                ->missing('page.items.0.customers')
                ->missing('companyContext'));
    }

    public function test_suspension_requires_recent_password_reason_and_confirmation(): void
    {
        $operator = $this->platformOwner();
        $target = $this->accountOwner('target@example.com');

        $this->actingAs($operator)
            ->post(route('platform.users.suspension.store', $target), [
                'reason' => 'Support request',
                'confirmed' => true,
            ])
            ->assertRedirect(route('password.confirm'));

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('platform.users.suspension.store', $target), [
                'confirmed' => false,
            ])
            ->assertSessionHasErrors(['reason', 'confirmed']);

        $this->post(route('platform.users.suspension.store', $target), [
            'reason' => 'Support request',
            'confirmed' => true,
        ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertNotNull($target->refresh()->suspended_at);
    }

    public function test_suspended_user_cannot_sign_in_and_existing_session_is_ended(): void
    {
        $user = $this->accountOwner('suspended@example.com');
        $user->forceFill(['suspended_at' => now()])->save();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $this->assertGuest();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_platform_pages_use_the_authenticated_users_language(): void
    {
        $operator = $this->platformOwner();
        $operator->forceFill(['language_code' => 'ro'])->save();

        $this->actingAs($operator)
            ->get(route('platform.audit.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/audit')
                ->where('i18n.locale', 'ro')
                ->where('translations.audit.title', 'Audit platformă'));
    }

    public function test_platform_audit_exposes_privacy_minimal_erasure_and_cleanup_evidence(): void
    {
        $operator = $this->platformOwner();
        $event = DataErasureEvent::query()->create([
            'actor_user_id' => null,
            'action' => DataErasureAction::CompanyErased,
            'subject_type' => 'COMPANY',
            'subject_id' => (string) Str::uuid7(),
            'occurred_at' => now(),
        ]);
        CompanyErasureFile::query()->create([
            'data_erasure_event_id' => $event->id,
            'storage_disk' => 'company_assets_local',
            'storage_key' => 'opaque/private.pdf',
            'storage_configuration_fingerprint' => str_repeat('a', 64),
            'attempt_count' => 1,
            'last_attempted_at' => now(),
            'last_failure_category' => 'STORAGE_UNAVAILABLE',
            'last_failure_summary' => 'Private file cleanup could not be confirmed.',
            'created_at' => now(),
        ]);

        $this->actingAs($operator)->get(route('platform.audit.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('erasurePage.items', 1)
                ->where('erasurePage.items.0.subjectId', $event->subject_id)
                ->where('erasurePage.items.0.pendingFileCount', 1)
                ->where('erasurePage.items.0.failedFileCount', 1)
                ->missing('erasurePage.items.0.storageKey'));
    }

    public function test_platform_erasure_evidence_cursor_retrieves_older_proof(): void
    {
        $operator = $this->platformOwner();
        $oldest = null;

        foreach (range(0, 25) as $minutes) {
            $event = DataErasureEvent::query()->create([
                'actor_user_id' => null,
                'action' => DataErasureAction::UserAccountErased,
                'subject_type' => 'USER_ACCOUNT',
                'subject_id' => (string) Str::uuid7(),
                'occurred_at' => now()->subMinutes($minutes),
            ]);
            $oldest = $event;
        }

        $this->actingAs($operator);
        $nextUrl = $this->get(route('platform.audit.index'))
            ->inertiaProps('erasurePage.nextUrl');

        $this->assertIsString($nextUrl);
        $this->get($nextUrl)->assertInertia(fn (Assert $page) => $page
            ->has('erasurePage.items', 1)
            ->where('erasurePage.items.0.id', $oldest?->id));
    }

    private function platformOwner(): User
    {
        $owner = $this->accountOwner('operator@example.com');
        PlatformOperator::query()->create([
            'user_id' => $owner->id,
            'role' => PlatformRole::Owner,
        ]);

        return $owner;
    }

    private function accountOwner(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        Account::query()->create([
            'owner_user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        return $user->load('account');
    }
}
