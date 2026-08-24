<?php

namespace App\Modules\Companies\Support;

use App\Modules\Companies\Data\ValidatedCompanyLogoUpload;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class CompanyLogoUploadPolicy
{
    /** @var array<string, string> */
    private const EXTENSIONS_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private ConfigRepository $config,
        private ValidationFactory $validator,
        private CompanyLogoRasterStructure $rasterStructure,
    ) {}

    public function inspect(UploadedFile $upload): ValidatedCompanyLogoUpload
    {
        $maximumBytes = (int) $this->config->get('invumo.company_assets.logo_max_bytes');
        $maximumWidth = (int) $this->config->get('invumo.company_assets.logo_max_width');
        $maximumHeight = (int) $this->config->get('invumo.company_assets.logo_max_height');

        $this->validator->make(['logo' => $upload], [
            'logo' => [
                'required',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max((int) ceil($maximumBytes / 1024))
                    ->dimensions(
                        Rule::dimensions()
                            ->maxWidth($maximumWidth)
                            ->maxHeight($maximumHeight),
                    ),
            ],
        ])->validate();

        $path = $upload->getRealPath();
        $contents = file_get_contents($path);
        $dimensions = @getimagesize($path);

        if ($contents === false || $dimensions === false) {
            throw ValidationException::withMessages(['logo' => __('validation.image')]);
        }

        $mimeType = $dimensions['mime'];
        $extension = self::EXTENSIONS_BY_MIME[$mimeType] ?? null;

        if ($extension === null || ! $this->rasterStructure->isValid($contents, $mimeType)) {
            throw ValidationException::withMessages(['logo' => __('validation.image')]);
        }

        $byteSize = strlen($contents);

        if ($byteSize < 1 || $byteSize > $maximumBytes) {
            throw ValidationException::withMessages(['logo' => __('validation.max.file', [
                'max' => (int) ceil($maximumBytes / 1024),
            ])]);
        }

        $contentSha256 = hash('sha256', $contents);

        if (strlen($contentSha256) !== 64) {
            throw new RuntimeException('Unable to calculate the Company asset content hash.');
        }

        return new ValidatedCompanyLogoUpload(
            contents: $contents,
            mimeType: $mimeType,
            extension: $extension,
            byteSize: $byteSize,
            contentSha256: $contentSha256,
            pixelWidth: $dimensions[0],
            pixelHeight: $dimensions[1],
        );
    }
}
