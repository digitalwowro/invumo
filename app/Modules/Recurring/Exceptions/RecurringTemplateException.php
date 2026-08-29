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

    public static function confirmationRequired(): self
    {
        return new self('confirmation_required');
    }

    public static function highRiskConfirmationRequired(): self
    {
        return new self('high_risk_confirmation_required');
    }

    public static function dependency(): self
    {
        return new self('dependency');
    }

    public static function completed(): self
    {
        return new self('completed');
    }

    public static function scheduleIncomplete(): self
    {
        return new self('schedule_incomplete');
    }

    public static function scheduleExhausted(): self
    {
        return new self('schedule_exhausted');
    }

    public static function transitionUnavailable(): self
    {
        return new self('transition_unavailable');
    }

    public static function activationIncomplete(): self
    {
        return new self('activation_incomplete');
    }

    public static function notCompleted(): self
    {
        return new self('not_completed');
    }

    public static function retryUnavailable(): self
    {
        return new self('retry_unavailable');
    }

    public function reason(): string
    {
        return $this->getMessage();
    }
}
