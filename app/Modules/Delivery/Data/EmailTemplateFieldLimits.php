<?php

namespace App\Modules\Delivery\Data;

final class EmailTemplateFieldLimits
{
    public const SUBJECT = 500;

    public const BODY = 20_000;

    public const BUTTON_LABEL = 80;

    public const SIGNATURE = 5_000;

    private function __construct() {}
}
