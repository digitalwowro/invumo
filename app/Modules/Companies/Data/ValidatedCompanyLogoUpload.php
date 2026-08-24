<?php

namespace App\Modules\Companies\Data;

final readonly class ValidatedCompanyLogoUpload
{
    public function __construct(
        public string $contents,
        public string $mimeType,
        public string $extension,
        public int $byteSize,
        public string $contentSha256,
        public int $pixelWidth,
        public int $pixelHeight,
    ) {}
}
