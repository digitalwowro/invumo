<?php

namespace App\Modules\Documents\Data;

enum DocumentAssignmentSource: string
{
    case Automatic = 'AUTOMATIC';
    case Manual = 'MANUAL';
}
