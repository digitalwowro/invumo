<?php

namespace App\Modules\Delivery\Contracts;

use RuntimeException;

final class ProviderWebhookRequestException extends RuntimeException
{
    public static function unauthorized(): self
    {
        return new self('unauthorized');
    }

    public static function malformed(): self
    {
        return new self('malformed');
    }
}
