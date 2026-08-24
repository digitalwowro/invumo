<?php

namespace App\Modules\Companies\Data;

final readonly class StoredCompanyAsset
{
    public function __construct(
        public string $id,
        public string $disk,
        public string $key,
    ) {}
}
