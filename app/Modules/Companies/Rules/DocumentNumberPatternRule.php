<?php

namespace App\Modules\Companies\Rules;

use App\Foundation\Documents\DocumentNumberPattern;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final class DocumentNumberPatternRule implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! DocumentNumberPattern::accepts($value)) {
            $fail(__('companies_ui.settings.numbering.errors.pattern_invalid'));
        }
    }
}
