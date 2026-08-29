<?php

namespace Tests\Feature\Modules\Companies;

use App\Modules\Audit\Data\DataErasureAction;
use App\Modules\Audit\Models\DataErasureEvent;
use App\Modules\Companies\Actions\CleanCompanyErasureFiles;
use App\Modules\Companies\Actions\ReconcileCompanyErasureFileCleanup;
use App\Modules\Companies\Actions\RecordCompanyErasureFiles;
use App\Modules\Companies\Data\ErasedCompanyFile;
use App\Modules\Companies\Exceptions\CompanyErasureFileCleanupIncomplete;
use App\Modules\Companies\Jobs\DeleteErasedCompanyFiles;
use App\Modules\Companies\Models\CompanyErasureFile;
use App\Modules\Companies\Support\ErasureStorageFingerprint;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class CompanyErasureFileCleanupTest extends TestCase
{
    use DatabaseMigrations;

    public function test_cleanup_is_isolated_per_file_and_retains_failed_coordinates(): void
    {
        Storage::fake('company_assets_local');
        config(['filesystems.disks.broken' => [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/broken'),
        ]]);
        Storage::disk('company_assets_local')->put('artifacts/clean.pdf', 'private');
        $event = $this->event();
        $this->record($event, [
            new ErasedCompanyFile('broken', 'artifacts/stuck.pdf'),
            new ErasedCompanyFile('company_assets_local', 'artifacts/clean.pdf'),
        ]);
        $filesystems = Mockery::mock(FilesystemManager::class);
        $filesystems->shouldReceive('disk')->with('broken')
            ->andThrow(new RuntimeException('Sensitive storage failure'));
        $filesystems->shouldReceive('disk')->with('company_assets_local')
            ->andReturn(Storage::disk('company_assets_local'));

        try {
            (new CleanCompanyErasureFiles(
                $filesystems,
                app(ErasureStorageFingerprint::class),
            ))->handle($event->id);
            $this->fail('The pending cleanup should request a retry.');
        } catch (CompanyErasureFileCleanupIncomplete) {
            // The successfully deleted file must still complete in the same attempt.
        }

        Storage::disk('company_assets_local')->assertMissing('artifacts/clean.pdf');
        $failed = CompanyErasureFile::query()->where('storage_disk', 'broken')->sole();
        $this->assertNull($failed->completed_at);
        $this->assertSame('STORAGE_UNAVAILABLE', $failed->last_failure_category);
        $this->assertStringNotContainsString('Sensitive', (string) $failed->last_failure_summary);
        $completed = CompanyErasureFile::query()->whereNotNull('completed_at')->sole();
        $this->assertNull($completed->storage_disk);
        $this->assertNull($completed->storage_key);
    }

    public function test_reconciliation_requeues_abandoned_cleanup_without_file_keys(): void
    {
        Queue::fake();
        $event = $this->event();
        $this->record($event, [new ErasedCompanyFile('company_assets_local', 'logos/private.png')]);
        CompanyErasureFile::query()->update(['last_attempted_at' => now()->subHours(7)]);

        $this->assertSame(1, app(ReconcileCompanyErasureFileCleanup::class)->handle());
        Queue::assertPushed(DeleteErasedCompanyFiles::class, function ($job) use ($event): bool {
            $payload = serialize($job);

            return $job->erasureEventId === $event->id
                && ! str_contains($payload, 'logos/private.png');
        });
    }

    public function test_completed_cleanup_evidence_cannot_be_reopened(): void
    {
        Storage::fake('company_assets_local');
        $event = $this->event();
        $this->record($event, [new ErasedCompanyFile('company_assets_local', 'already-absent.pdf')]);
        app(CleanCompanyErasureFiles::class)->handle($event->id);

        $this->expectException(QueryException::class);
        CompanyErasureFile::query()->sole()->update([
            'completed_at' => null,
            'storage_disk' => 'company_assets_local',
            'storage_key' => 'restored.pdf',
        ]);
    }

    public function test_runtime_cannot_delete_retained_cleanup_evidence(): void
    {
        $event = $this->event();
        $this->record($event, [new ErasedCompanyFile('company_assets_local', 'retained.pdf')]);

        $this->expectException(QueryException::class);
        CompanyErasureFile::query()->sole()->delete();
    }

    public function test_runtime_cannot_delete_the_erasure_proof(): void
    {
        $event = $this->event();

        $this->expectException(QueryException::class);
        $event->delete();
    }

    public function test_storage_location_change_keeps_the_manifest_pending(): void
    {
        Storage::fake('company_assets_local');
        $event = $this->event();
        $this->record($event, [new ErasedCompanyFile('company_assets_local', 'old-root.pdf')]);
        config(['filesystems.disks.company_assets_local.root' => storage_path('moved-assets')]);

        $this->expectException(CompanyErasureFileCleanupIncomplete::class);

        try {
            app(CleanCompanyErasureFiles::class)->handle($event->id);
        } finally {
            $file = CompanyErasureFile::query()->sole();
            $this->assertNull($file->completed_at);
            $this->assertSame('old-root.pdf', $file->storage_key);
            $this->assertSame('STORAGE_CONFIGURATION_CHANGED', $file->last_failure_category);
        }
    }

    private function event(): DataErasureEvent
    {
        return DataErasureEvent::query()->create([
            'actor_user_id' => null,
            'action' => DataErasureAction::CompanyErased,
            'subject_type' => 'COMPANY',
            'subject_id' => (string) Str::uuid7(),
            'occurred_at' => now(),
        ]);
    }

    /** @param list<ErasedCompanyFile> $files */
    private function record(DataErasureEvent $event, array $files): void
    {
        DB::connection(config('database.tenant_connection'))->transaction(
            fn (): int => app(RecordCompanyErasureFiles::class)->handle($event->id, $files),
        );
    }
}
