<?php

namespace App\Support\Inertia;

use Illuminate\Contracts\Translation\Translator;
use RuntimeException;

final readonly class SettingsUiTranslationBag
{
    public function __construct(private Translator $translator) {}

    /**
     * @return array{layout: array<string, mixed>, shared: array<string, mixed>, page: array<string, mixed>}
     */
    public function for(string $page, ?string $locale = null): array
    {
        $layout = $this->translator->get('settings_ui.layout', locale: $locale);
        $shared = $this->translator->get('settings_ui.shared', locale: $locale);
        $pageTranslations = $this->translator->get("settings_ui.pages.{$page}", locale: $locale);

        if (! is_array($layout) || ! is_array($shared) || ! is_array($pageTranslations)) {
            throw new RuntimeException("The settings translation bag [{$page}] is incomplete.");
        }

        return [
            'layout' => $layout,
            'shared' => $shared,
            'page' => $pageTranslations,
        ];
    }
}
