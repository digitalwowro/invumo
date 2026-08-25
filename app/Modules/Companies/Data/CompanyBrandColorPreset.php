<?php

namespace App\Modules\Companies\Data;

enum CompanyBrandColorPreset: string
{
    case Ink = '#14181C';
    case Navy = '#1E3A5F';
    case Forest = '#1F5D42';
    case Burgundy = '#7F1D1D';
    case Violet = '#5B3A8E';

    public function translationKey(): string
    {
        return strtolower($this->name);
    }
}
