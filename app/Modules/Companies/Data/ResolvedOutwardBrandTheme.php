<?php

namespace App\Modules\Companies\Data;

final readonly class ResolvedOutwardBrandTheme
{
    public function __construct(
        public string $accentColor,
        public string $onAccentColor,
        public string $textColor,
        public string $ruleColor,
    ) {}
}
