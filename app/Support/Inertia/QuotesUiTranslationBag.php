<?php

namespace App\Support\Inertia;

use Illuminate\Contracts\Translation\Translator;
use RuntimeException;

final readonly class QuotesUiTranslationBag
{
    public function __construct(private Translator $translator) {}

    /** @return array<string, mixed> */
    public function toArray(?string $locale = null): array
    {
        $translations = $this->translator->get('quotes_ui', locale: $locale);

        if (! is_array($translations)) {
            throw new RuntimeException('The Quotes UI translation catalogue must resolve to an array.');
        }

        return $translations;
    }
}
