<?php

namespace App\Modules\Companies\Data;

final readonly class ErasedCompanyFile
{
    public function __construct(
        public string $disk,
        public string $key,
    ) {}
}
