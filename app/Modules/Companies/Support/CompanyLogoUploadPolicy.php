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

        if ($extension === null || $this->isAnimated($contents, $mimeType)) {
            throw ValidationException::withMessages(['logo' => __('validation.image')]);
        }

        $decodedImage = @imagecreatefromstring($contents);

        if ($decodedImage === false) {
            throw ValidationException::withMessages(['logo' => __('validation.image')]);
        }

        unset($decodedImage);

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

    private function isAnimated(string $contents, string $mimeType): bool
    {
        return match ($mimeType) {
            'image/png' => $this->containsPngChunk($contents, 'acTL'),
            'image/webp' => $this->containsWebpChunk($contents, ['ANIM', 'ANMF']),
            default => false,
        };
    }

    private function containsPngChunk(string $contents, string $target): bool
    {
        $offset = 8;
        $length = strlen($contents);

        while ($offset + 12 <= $length) {
            $chunkHeader = unpack('Nlength', substr($contents, $offset, 4));

            if ($chunkHeader === false) {
                return false;
            }

            $chunkSize = $chunkHeader['length'];
            $chunkType = substr($contents, $offset + 4, 4);

            if ($chunkType === $target) {
                return true;
            }

            $offset += 12 + $chunkSize;
        }

        return false;
    }

    /** @param list<string> $targets */
    private function containsWebpChunk(string $contents, array $targets): bool
    {
        if (substr($contents, 0, 4) !== 'RIFF' || substr($contents, 8, 4) !== 'WEBP') {
            return false;
        }

        $offset = 12;
        $length = strlen($contents);

        while ($offset + 8 <= $length) {
            $chunkType = substr($contents, $offset, 4);
            $chunkHeader = unpack('Vlength', substr($contents, $offset + 4, 4));

            if ($chunkHeader === false) {
                return false;
            }

            $chunkSize = $chunkHeader['length'];

            if (in_array($chunkType, $targets, true)) {
                return true;
            }

            $offset += 8 + $chunkSize + ($chunkSize % 2);
        }

        return false;
    }
}
