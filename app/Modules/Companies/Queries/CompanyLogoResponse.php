<?php

namespace App\Modules\Companies\Queries;

use App\Models\User;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Policies\CompanyAuthorization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Filesystem\FilesystemManager;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class CompanyLogoResponse
{
    public function __construct(
        private CompanyAuthorization $authorization,
        private FilesystemManager $filesystems,
    ) {}

    public function for(Company $company, User $actor): StreamedResponse
    {
        $membership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $actor->id)
            ->first();

        if ($membership === null
            || ! $this->authorization->allows($membership->role, CompanyAbility::ManageCompanySettings)
        ) {
            throw new AuthorizationException;
        }

        $asset = CompanySetting::query()
            ->with(['logoAsset' => fn ($query) => $query->whereNull('deleted_at')])
            ->firstOrFail()
            ->logoAsset;

        abort_if($asset === null, 404);
        $disk = $this->filesystems->disk($asset->storage_disk);
        abort_unless($disk->exists($asset->storage_key), 404);

        return response()->stream(
            function () use ($disk, $asset): void {
                $stream = $disk->readStream($asset->storage_key);

                if (! is_resource($stream)) {
                    throw new RuntimeException('The Company logo could not be read from private storage.');
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
}
