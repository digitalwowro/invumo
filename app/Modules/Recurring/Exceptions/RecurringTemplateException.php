<?php

namespace App\Modules\Recurring\Exceptions;

use DomainException;

final class RecurringTemplateException extends DomainException
{
    public static function stale(): self
    {
        return new self('stale');
    }

    public static function customerDefaultsChanged(): self
    {
        return new self('customer_defaults_changed');
    }

    public static function sourceUnavailable(): self
    {
        return new self('source_unavailable');
    }

    public static function lineSetInvalid(): self
    {
        return new self('line_set_invalid');
    }

    public static function notDraft(): self
    {
        return new self('not_draft');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
