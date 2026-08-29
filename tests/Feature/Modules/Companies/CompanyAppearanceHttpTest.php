<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Jobs\TenantJobExecution;
use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Jobs\DeleteUnreferencedCompanyLogo;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyAsset;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Support\CompanyAssetStorage;
use App\Modules\Companies\Support\OutwardBrandTheme;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CompanyAppearanceHttpTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('company_assets_local');
        config()->set('invumo.company_assets.disk', 'company_assets_local');
    }

    public function test_owner_sees_localized_defaults_presets_and_navigation(): void
    {
        [$owner, $company] = $this->company();

        $this->actingAs($owner)
            ->get(route('company-appearance.edit', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->component('companies/settings/appearance')
                ->where('appearance.primaryBrandColor', '#14181C')
                ->where('appearance.logo', null)
                ->where('brandColorPresets.0.value', '#14181C')
                ->where('brandColorPresets.4.value', '#5B3A8E')
                ->where('companySettingsNavigation.7.key', 'appearance')
                ->where('translations.settings.appearance.presets.forest', 'Forest'));

        $owner->update(['language_code' => 'ro']);
        $this->get(route('company-appearance.edit', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('translations.settings.layout.navigation.appearance', 'Aspect')
                ->where('translations.settings.appearance.logo_title', 'Sigla companiei'));
    }

    public function test_owner_saves_and_privately_serves_a_logo_with_bounded_audit_data(): void
    {
        [$owner, $company] = $this->company();
        $logo = UploadedFile::fake()->image('brand.png', 8, 6);

        $this->actingAs($owner)->post(route('company-appearance.update', $company), [
            'primary_brand_color' => ' #1e3a5f ',
            'logo' => $logo,
            'remove_logo' => false,
        ])->assertRedirect()->assertSessionHas('status');

        [$settings, $asset, $event] = app(TenantContext::class)->runAsSystem(
            $company->id,
            fn (): array => [
                CompanySetting::query()->firstOrFail(),
                CompanyAsset::query()->firstOrFail(),
                AuditEvent::query()->where('action', 'company.appearance.updated')->firstOrFail(),
            ],
        );

        $this->assertSame('#1E3A5F', $settings->primary_brand_color);
        $this->assertSame($asset->id, $settings->logo_asset_id);
        Storage::disk($asset->storage_disk)->assertExists($asset->storage_key);
        $this->assertEqualsCanonicalizing(
            ['changed_fields', 'primary_brand_color', 'has_logo'],
            array_keys($event->after ?? []),
        );
        $auditJson = json_encode([$event->before, $event->after], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($asset->storage_key, $auditJson);
        $this->assertStringNotContainsString($asset->content_sha256, $auditJson);

        $this->get(route('company-appearance.edit', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('appearance.logo.previewUrl', route('company-appearance.logo', $company, false)));

        $response = $this->get(route('company-appearance.logo', $company));
        $response->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame($logo->getContent(), $response->streamedContent());
    }

    public function test_replacement_and_removal_cleanup_only_unreferenced_immutable_assets(): void
    {
        Queue::fake();
        [$owner, $company] = $this->company();
        $this->actingAs($owner);
        $this->save($company, UploadedFile::fake()->image('first.png', 2, 2));
        $first = $this->asset($company);

        $this->save($company, UploadedFile::fake()->image('second.png', 3, 3));
        $second = $this->asset($company);
        $this->assertNotSame($first->id, $second->id);
        Queue::assertPushed(
            DeleteUnreferencedCompanyLogo::class,
            fn (DeleteUnreferencedCompanyLogo $job): bool => $job->assetId === $first->id,
        );
        $this->runCleanup(new DeleteUnreferencedCompanyLogo($company->id, $first->id));
        Storage::disk($first->storage_disk)->assertMissing($first->storage_key);
        app(TenantContext::class)->runAsSystem(
            $company->id,
            fn () => $this->assertNotNull(CompanyAsset::query()->findOrFail($first->id)->deleted_at),
        );

        $this->post(route('company-appearance.update', $company), [
            'primary_brand_color' => '#1E3A5F',
            'remove_logo' => true,
        ])->assertRedirect();
        $this->runCleanup(new DeleteUnreferencedCompanyLogo($company->id, $second->id));
        Storage::disk($second->storage_disk)->assertMissing($second->storage_key);
        $this->get(route('company-appearance.logo', $company))->assertNotFound();
    }

    public function test_cleanup_skips_a_still_referenced_logo(): void
    {
        [$owner, $company] = $this->company();
        $this->actingAs($owner);
        $this->save($company, UploadedFile::fake()->image('current.png', 2, 2));
        $asset = $this->asset($company);
        $job = new DeleteUnreferencedCompanyLogo($company->id, $asset->id);
        $execution = app(TenantJobExecution::class);

        $execution->run(
            $job->identity,
            '018f0000-0000-7000-8000-000000000001',
            1,
            $job->tries,
            fn () => $job->handle(
                app(TenantContext::class),
                $execution,
                app(CompanyAssetStorage::class),
            ),
        );

        Storage::disk($asset->storage_disk)->assertExists($asset->storage_key);
        app(TenantContext::class)->runAsSystem(
            $company->id,
            fn () => $this->assertNull(CompanyAsset::query()->findOrFail($asset->id)->deleted_at),
        );
    }

    public function test_admin_is_allowed_while_member_and_cross_company_access_are_denied(): void
    {
        [$owner, $company] = $this->company();
        [$outsider, $other] = $this->company('outsider@example.com', 'Other SRL');
        $admin = $this->user('admin@example.com');
        $member = $this->user('member@example.com');
        $company->memberships()->create(['user_id' => $admin->id, 'role' => CompanyRole::Admin]);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);

        $this->actingAs($admin)->post(route('company-appearance.update', $company), [
            'primary_brand_color' => '#1F5D42',
            'remove_logo' => false,
        ])->assertRedirect();
        $this->actingAs($member)->get(route('company-appearance.edit', $company))->assertForbidden();
        $this->post(route('company-appearance.update', $company), [
            'primary_brand_color' => '#7F1D1D',
            'remove_logo' => false,
        ])->assertForbidden();
        $this->get(route('company-appearance.logo', $company))->assertForbidden();
        $this->actingAs($owner)->get(route('company-appearance.edit', $other))->assertNotFound();
        $this->assertNotNull($outsider);
    }

    public function test_validation_rejects_invalid_colors_and_conflicting_logo_operations(): void
    {
        [$owner, $company] = $this->company();
        $this->actingAs($owner);

        $this->post(route('company-appearance.update', $company), [
            'primary_brand_color' => '#GGGGGG',
            'logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg/>'),
            'remove_logo' => true,
        ])->assertSessionHasErrors(['primary_brand_color', 'logo']);

        app(TenantContext::class)->runAsSystem($company->id, function (): void {
            $this->assertSame('#14181C', CompanySetting::query()->firstOrFail()->primary_brand_color);
            $this->assertSame(0, CompanyAsset::query()->count());
            $this->assertSame(0, AuditEvent::query()->where('action', 'company.appearance.updated')->count());
        });
    }

    public function test_database_constraints_and_rls_independently_reject_unsafe_writes(): void
    {
        [, $company] = $this->company();
        [, $other] = $this->company('other@example.com', 'Other SRL');

        app(TenantContext::class)->runAsSystem($company->id, function () use ($other): void {
            $crossCompanyUpdates = CompanySetting::query()
                ->withoutGlobalScope('company')
                ->where('company_id', $other->id)
                ->update(['primary_brand_color' => '#1E3A5F']);

            $this->assertSame(0, $crossCompanyUpdates);

            try {
                CompanySetting::query()->firstOrFail()->update([
                    'primary_brand_color' => '#abcdef',
                ]);
                $this->fail('The database must reject noncanonical brand colors.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        });
    }

    public function test_database_default_matches_the_shared_outward_theme_contract(): void
    {
        $contents = file_get_contents(
            base_path('tests/Fixtures/Branding/outward-brand-theme-vectors.json'),
        );
        $this->assertNotFalse($contents);
        $fixture = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        $columnDefault = DB::connection('pgsql_schema')
            ->table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'company_settings')
            ->where('column_name', 'primary_brand_color')
            ->value('column_default');

        $this->assertSame(OutwardBrandTheme::DEFAULT_COLOR, $fixture['contract']['default_color']);
        $this->assertSame("'{$fixture['contract']['default_color']}'::text", $columnDefault);
    }

    private function save(Company $company, UploadedFile $logo): void
    {
        $this->post(route('company-appearance.update', $company), [
            'primary_brand_color' => '#1E3A5F',
            'logo' => $logo,
            'remove_logo' => false,
        ])->assertRedirect()->assertSessionDoesntHaveErrors();
    }

    private function asset(Company $company): CompanyAsset
    {
        return app(TenantContext::class)->runAsSystem(
            $company->id,
            fn (): CompanyAsset => CompanySetting::query()->firstOrFail()->logoAsset()->firstOrFail(),
        );
    }

    private function runCleanup(DeleteUnreferencedCompanyLogo $job): void
    {
        $job->handle(
            app(TenantContext::class),
            app(TenantJobExecution::class),
            app(CompanyAssetStorage::class),
        );
    }

    /** @return array{User, Company} */
    private function company(
        string $email = 'owner@example.com',
        string $name = 'Acme SRL',
    ): array {
        $owner = $this->user($email);

        return [$owner, app(CreateCompany::class)->handle(
            $owner->account()->firstOrFail(),
            $owner,
            $name,
        )];
    }

    private function user(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        Account::query()->create(['owner_user_id' => $user->id, 'plan_id' => $plan->id]);

        return $user;
    }
}
