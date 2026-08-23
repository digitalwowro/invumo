<?php

namespace App\Support\Inertia;

use Illuminate\Contracts\Translation\Translator;
use RuntimeException;

final readonly class AuthUiTranslationBag
{
    public function __construct(private Translator $translator) {}

    /**
     * @return array{shared: array<string, mixed>, page: array<string, mixed>}
     */
    public function for(string $page, ?string $locale = null): array
    {
        $shared = $this->translator->get('auth_ui.shared', locale: $locale);
        $pageTranslations = $this->translator->get("auth_ui.pages.{$page}", locale: $locale);

        if (! is_array($shared) || ! is_array($pageTranslations)) {
            throw new RuntimeException("The auth translation bag [{$page}] is incomplete.");
        }

        return [
            'shared' => $shared,
            'page' => $pageTranslations,
        ];
    }
}
