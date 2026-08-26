<?php

namespace App\Modules\Documents\Data;

enum DocumentNumberEventType: string
{
    case Assigned = 'ASSIGNED';
    case RenamedFrom = 'RENAMED_FROM';
    case RenamedTo = 'RENAMED_TO';
    case Deleted = 'DELETED';
}
