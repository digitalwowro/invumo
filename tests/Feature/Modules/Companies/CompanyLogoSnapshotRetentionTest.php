<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Jobs\TenantJobExecution;
use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Jobs\DeleteUnreferencedCompanyLogo;
use App\Modules\Companies\Models\CompanyAsset;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Support\CompanyAssetStorage;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Quotes\Actions\CreateQuoteDraft;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CompanyLogoSnapshotRetentionTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('company_assets_local');
        config()->set('invumo.company_assets.disk', 'company_assets_local');
    }

    public function test_replaced_logo_remains_available_while_a_document_snapshot_references_it(): void
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Snapshot Logo SRL');
        app(TenantContext::class)->runAsSystem(
            $company->id,
            fn () => CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'Europe/Bucharest',
            ]),
        );
        $this->actingAs($owner);
        $this->saveLogo($company->id, UploadedFile::fake()->image('first.png', 8, 6));
        $first = $this->currentAsset($company->id);
        $quote = app(CreateQuoteDraft::class)->handle($company, $owner, (string) Str::uuid7());

        $this->saveLogo($company->id, UploadedFile::fake()->image('second.png', 10, 8));
        $job = new DeleteUnreferencedCompanyLogo($company->id, $first->id);
        $execution = app(TenantJobExecution::class);
        $execution->run(
            $job->identity,
            (string) Str::uuid7(),
            1,
            $job->tries,
            fn () => $job->handle(
                app(TenantContext::class),
                $execution,
                app(CompanyAssetStorage::class),
            ),
        );

        Storage::disk($first->storage_disk)->assertExists($first->storage_key);
        app(TenantContext::class)->runAsSystem($company->id, function () use ($first): void {
            $this->assertNull(CompanyAsset::query()->findOrFail($first->id)->deleted_at);

            try {
                DB::connection(config('database.tenant_connection'))->transaction(
                    fn () => CompanyAsset::query()->whereKey($first->id)->update([
                        'deleted_at' => now(),
                    ]),
                );
                $this->fail('The database must preserve an asset referenced by a document snapshot.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->errorInfo[0]);
            }
        });
        $this->get(route('quotes.current.logo', [$company, $quote]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    private function saveLogo(string $companyId, UploadedFile $logo): void
    {
        $this->post(route('company-appearance.update', $companyId), [
            'primary_brand_color' => '#14181C',
            'logo' => $logo,
            'remove_logo' => false,
        ])->assertRedirect()->assertSessionDoesntHaveErrors();
    }

    private function currentAsset(string $companyId): CompanyAsset
    {
        return app(TenantContext::class)->runAsSystem(
            $companyId,
            fn (): CompanyAsset => CompanySetting::query()->firstOrFail()->logoAsset()->firstOrFail(),
        );
    }
}
