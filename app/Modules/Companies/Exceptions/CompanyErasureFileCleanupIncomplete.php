<?php

namespace App\Modules\Companies\Exceptions;

use RuntimeException;

final class CompanyErasureFileCleanupIncomplete extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('One or more erased Company files still require cleanup.');
    }
}
