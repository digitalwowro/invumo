<?php

namespace App\Modules\Companies\Exceptions;

use RuntimeException;

final class ErasureStorageConfigurationChanged extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Erased Company storage location changed before cleanup.');
    }
}
