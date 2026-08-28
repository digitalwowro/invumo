<?php

namespace App\Modules\Delivery\Exceptions;

use RuntimeException;

final class RetryableProviderRejection extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The provider rejected the delivery temporarily.');
    }
}
