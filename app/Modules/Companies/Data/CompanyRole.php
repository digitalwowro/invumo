<?php

namespace App\Modules\Companies\Data;

enum CompanyRole: string
{
    case Owner = 'OWNER';
    case Admin = 'ADMIN';
    case Member = 'MEMBER';
}
