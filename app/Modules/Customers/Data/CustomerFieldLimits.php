<?php

namespace App\Modules\Customers\Data;

final class CustomerFieldLimits
{
    public const NAME = 160;

    public const EMAIL = 254;

    public const PHONE = 50;

    public const EXTERNAL_REFERENCE = 120;

    public const ADDRESS_LINE = 200;

    public const LOCALITY = 120;

    public const POSTAL_CODE = 32;

    public const REGISTRATION_LABEL = 80;

    public const REGISTRATION_VALUE = 120;

    public const INTERNAL_NOTES = 5000;

    public const SEARCH = 120;
}
