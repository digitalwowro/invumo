<?php

namespace App\Modules\Recurring\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/** @implements CastsAttributes<list<string>, list<string>> */
final class RecurringExplicitFields implements CastsAttributes
{
    private const ALLOWED = [
        'identity', 'recipients', 'currency', 'document_language',
        'payment_term_days', 'tax_default', 'email_attachment_mode',
    ];

    /** @return list<string> */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (! is_string($value) || $value === '{}') {
            return [];
        }

        return explode(',', trim($value, '{}'));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('Recurring explicit fields must be an array.');
        }

        $fields = array_values(array_unique($value));

        foreach ($fields as $field) {
            if (! in_array($field, self::ALLOWED, true)) {
                throw new InvalidArgumentException('Unknown recurring explicit field.');
            }
        }

        return '{'.implode(',', $fields).'}';
    }
}
