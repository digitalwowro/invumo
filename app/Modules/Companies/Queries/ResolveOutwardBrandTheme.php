<?php

namespace App\Modules\Companies\Queries;

use App\Modules\Companies\Data\ResolvedOutwardBrandTheme;
use App\Modules\Companies\Support\OutwardBrandTheme;

final class ResolveOutwardBrandTheme
{
    public function for(string $color): ResolvedOutwardBrandTheme
    {
        return OutwardBrandTheme::resolve($color);
    }
}
