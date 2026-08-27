<?php

namespace App\Support\Inertia;

use Illuminate\Contracts\Translation\Translator;
use RuntimeException;

final readonly class TransactionsUiTranslationBag
{
    public function __construct(private Translator $translator) {}

    /** @return array<string, mixed> */
    public function toArray(?string $locale = null): array
    {
        $translations = $this->translator->get('transactions_ui', locale: $locale);

        if (! is_array($translations)) {
            throw new RuntimeException('The Transactions UI translation catalogue must resolve to an array.');
        }

        return $translations;
    }
}
