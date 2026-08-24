<?php

namespace App\Support\Inertia;

use Illuminate\Contracts\Translation\Translator;
use RuntimeException;

final readonly class ErrorPageTranslationBag
{
    public function __construct(private Translator $translator) {}

    /**
     * @return array{page: array{headTitle: string, title: string, description: string, action: string}}
     */
    public function for(int $status, ?string $locale = null): array
    {
        $page = $this->translator->get("errors.pages.{$status}", locale: $locale);

        if (
            ! is_array($page)
            || ! is_string($page['headTitle'] ?? null)
            || ! is_string($page['title'] ?? null)
            || ! is_string($page['description'] ?? null)
            || ! is_string($page['action'] ?? null)
        ) {
            throw new RuntimeException("The error translation bag [{$status}] is incomplete.");
        }

        return ['page' => [
            'headTitle' => $page['headTitle'],
            'title' => $page['title'],
            'description' => $page['description'],
            'action' => $page['action'],
        ]];
    }
}
