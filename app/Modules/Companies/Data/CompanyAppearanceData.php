<?php

namespace App\Modules\Companies\Data;

use Illuminate\Http\UploadedFile;

final readonly class CompanyAppearanceData
{
    public function __construct(
        public string $primaryBrandColor,
        public ?UploadedFile $logo,
        public bool $removeLogo,
    ) {}
}
