<?php

namespace App\Modules\Companies\Support;

use App\Modules\Companies\Data\StoredCompanyAsset;
use App\Modules\Companies\Data\ValidatedCompanyLogoUpload;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class CompanyAssetStorage
{
    public function __construct(
        private ConfigRepository $config,
        private FilesystemManager $filesystems,
    ) {}

    public function storeLogo(
        string $companyId,
        ValidatedCompanyLogoUpload $upload,
    ): StoredCompanyAsset {
        $diskName = $this->diskName();
        $assetId = (string) Str::uuid7();
        $key = "companies/{$companyId}/assets/{$assetId}.{$upload->extension}";
        $disk = $this->filesystems->disk($diskName);

        $written = $disk->put($key, $upload->contents, ['visibility' => 'private']);

        if (! $written) {
            throw new RuntimeException('The Company asset could not be written to private storage.');
        }

        try {
            $storedContents = $disk->get($key);

            if (
                $disk->size($key) !== $upload->byteSize
                || ! hash_equals($upload->contentSha256, hash('sha256', $storedContents))
            ) {
                throw new RuntimeException('The stored Company asset failed its integrity check.');
            }
        } catch (\Throwable $exception) {
            $disk->delete($key);

            throw $exception;
        }

        return new StoredCompanyAsset($assetId, $diskName, $key);
    }

    public function delete(StoredCompanyAsset $asset): void
    {
        $this->filesystems->disk($asset->disk)->delete($asset->key);
    }

    private function diskName(): string
    {
        $disk = $this->config->get('invumo.company_assets.disk');

        if (! is_string($disk) || $disk === '' || $disk === 'public') {
            throw new RuntimeException('Company assets require a configured private filesystem disk.');
        }

        return $disk;
    }
}
