<?php

namespace App\Support\Inertia;

use Illuminate\Contracts\Translation\Translator;
use RuntimeException;

final readonly class CommonTranslationBag
{
    public function __construct(private Translator $translator) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(?string $locale = null): array
    {
        $translations = $this->translator->get('common', locale: $locale);

        if (! is_array($translations)) {
            throw new RuntimeException('The common translation catalogue must resolve to an array.');
        }

        return $translations;
    }
}
