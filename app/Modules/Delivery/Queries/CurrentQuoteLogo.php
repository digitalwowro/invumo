<?php

namespace App\Modules\Delivery\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyAsset;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentCompanySnapshot;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class CurrentQuoteLogo
{
    public function __construct(
        private CompanyAbilityCheck $abilities,
        private FilesystemManager $filesystems,
    ) {}

    public function dataUri(Company $company, User $actor, string $documentId): ?string
    {
        $asset = $this->asset($company, $actor, $documentId);

        if ($asset === null) {
            return null;
        }

        $contents = $this->disk($asset)->get($asset->storage_key);

        return 'data:'.$asset->mime_type.';base64,'.base64_encode($contents);
    }

    public function response(Company $company, User $actor, string $documentId): StreamedResponse
    {
        $asset = $this->asset($company, $actor, $documentId);
        abort_if($asset === null, 404);
        $disk = $this->disk($asset);

        return response()->stream(
            function () use ($disk, $asset): void {
                $stream = $disk->readStream($asset->storage_key);

                if (! is_resource($stream)) {
                    throw new RuntimeException('The document logo could not be read from private storage.');
                }

                try {
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => $asset->mime_type,
                'Content-Length' => (string) $asset->byte_size,
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function asset(Company $company, User $actor, string $documentId): ?CompanyAsset
    {
        if (! $this->abilities->allows($actor, $company, CompanyAbility::ViewQuotes)) {
            throw new AuthorizationException;
        }

        $document = Document::query()
            ->whereKey($documentId)
            ->where('kind', DocumentKind::Quote)
            ->firstOrFail();
        $snapshot = DocumentCompanySnapshot::query()
            ->where('document_id', $document->id)
            ->firstOrFail();

        return $snapshot->logo_asset_id === null
            ? null
            : CompanyAsset::query()
                ->whereKey($snapshot->logo_asset_id)
                ->whereNull('deleted_at')
                ->first();
    }

    private function disk(CompanyAsset $asset): Filesystem
    {
        $disk = $this->filesystems->disk($asset->storage_disk);
        abort_unless($disk->exists($asset->storage_key), 404);

        return $disk;
    }
}
