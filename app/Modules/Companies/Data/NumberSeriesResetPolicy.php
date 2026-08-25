<?php

namespace App\Modules\Companies\Data;

enum NumberSeriesResetPolicy: string
{
    case Never = 'NEVER';
    case Annual = 'ANNUAL';
}
