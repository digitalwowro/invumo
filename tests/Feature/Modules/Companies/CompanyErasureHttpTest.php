<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Data\DataErasureAction;
use App\Modules\Audit\Models\DataErasureEvent;
use App\Modules\Companies\Actions\CleanCompanyErasureFiles;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyAssetPurpose;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Jobs\DeleteErasedCompanyFiles;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyAsset;
use App\Modules\Companies\Models\CompanyErasureFile;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CompanyErasureHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_owner_erases_company_data_and_queues_file_cleanup(): void
    {
        Queue::fake();
        Storage::fake('company_assets_local');
        [$owner, $company] = $this->company('Erase Me SRL');
        Storage::disk('company_assets_local')->put('logos/erase.png', 'logo');
        $this->tenant($company, function () use ($owner): void {
            Customer::query()->create(['type' => 'COMPANY', 'legal_name' => 'Private Customer']);
            CompanyAsset::query()->create([
                'purpose' => CompanyAssetPurpose::CompanyLogo,
                'storage_disk' => 'company_assets_local',
                'storage_key' => 'logos/erase.png',
                'mime_type' => 'image/png',
                'byte_size' => 4,
                'content_sha256' => hash('sha256', 'logo'),
                'pixel_width' => 1,
                'pixel_height' => 1,
                'created_by_user_id' => $owner->id,
            ]);
        });
        $this->actingAs($owner);
        $state = $this->get(route('company-data-lifecycle.show', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->component('companies/settings/data-lifecycle')
                ->where('erasure.guard.blocked', false))
            ->inertiaProps('erasure.stateVersion');

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('company-data-lifecycle.destroy', $company), [
                'confirmed' => true,
                'confirmed_high_risk' => true,
                'confirmation_name' => 'Erase Me SRL',
                'deletion_state' => $state,
            ])->assertRedirect(route('companies.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
        $this->assertDatabaseMissing('customers', ['company_id' => $company->id]);
        $event = DataErasureEvent::query()->sole();
        $this->assertSame(DataErasureAction::CompanyErased, $event->action);
        $this->assertSame($company->id, $event->subject_id);
        $this->assertSame($owner->id, $event->actor_user_id);
        $cleanup = CompanyErasureFile::query()->sole();
        $this->assertSame($event->id, $cleanup->data_erasure_event_id);
        $this->assertSame('logos/erase.png', $cleanup->storage_key);
        Queue::assertPushed(DeleteErasedCompanyFiles::class, function ($job): bool {
            $job->handle(app(CleanCompanyErasureFiles::class));

            return $job->erasureEventId !== '';
        });
        Storage::disk('company_assets_local')->assertMissing('logos/erase.png');
        $this->assertDatabaseHas('company_erasure_files', [
            'id' => $cleanup->id,
            'storage_disk' => null,
            'storage_key' => null,
        ]);
    }

    public function test_only_owner_can_open_or_submit_company_erasure(): void
    {
        [$owner, $company] = $this->company('Protected SRL');
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $company->memberships()->create(['user_id' => $admin->id, 'role' => CompanyRole::Admin]);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);

        foreach ([$admin, $member] as $actor) {
            $this->actingAs($actor)->get(route('company-data-lifecycle.show', $company))
                ->assertForbidden();
            $this->withSession(['auth.password_confirmed_at' => time()])
                ->delete(route('company-data-lifecycle.destroy', $company), [
                    'confirmed' => true,
                    'confirmed_high_risk' => true,
                    'confirmation_name' => $company->name,
                    'deletion_state' => str_repeat('0', 64),
                ])->assertForbidden();
        }

        $this->assertNotNull($company->fresh());
        $this->assertNotNull($owner->fresh());
    }

    public function test_company_change_rejects_stale_erasure_confirmation(): void
    {
        [$owner, $company] = $this->company('Original SRL');
        $this->actingAs($owner);
        $state = $this->get(route('company-data-lifecycle.show', $company))
            ->inertiaProps('erasure.stateVersion');
        Company::query()->whereKey($company->id)->update(['name' => 'Changed SRL']);

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('company-data-lifecycle.destroy', $company), [
                'confirmed' => true,
                'confirmed_high_risk' => true,
                'confirmation_name' => 'Original SRL',
                'deletion_state' => $state,
            ])->assertSessionHasErrors('company');

        $this->assertNotNull($company->fresh());
    }

    /** @return array{User, Company} */
    private function company(string $name): array
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
