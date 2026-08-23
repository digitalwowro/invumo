<?php

/**
 * @param  array<string, mixed>  $translations
 * @return array<string, string>
 */
function flattenTranslations(array $translations, string $prefix = ''): array
{
    $flattened = [];

    foreach ($translations as $key => $value) {
        $path = $prefix === '' ? $key : "{$prefix}.{$key}";

        if (is_array($value)) {
            $flattened += flattenTranslations($value, $path);

            continue;
        }

        $flattened[$path] = $value;
    }

    return $flattened;
}

/**
 * @return list<string>
 */
function translationPlaceholders(string $translation): array
{
    preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $translation, $matches);

    $placeholders = array_values(array_unique($matches[1]));
    sort($placeholders);

    return $placeholders;
}

it('keeps English and Romanian authored translation keys and placeholders aligned', function () {
    $projectRoot = dirname(__DIR__, 3);

    foreach (['auth_ui', 'common', 'companies_ui', 'company_invitations_mail', 'dashboard', 'design_system', 'settings_ui'] as $catalogue) {
        /** @var array<string, mixed> $english */
        $english = require "{$projectRoot}/lang/en/{$catalogue}.php";
        /** @var array<string, mixed> $romanian */
        $romanian = require "{$projectRoot}/lang/ro/{$catalogue}.php";

        $english = flattenTranslations($english);
        $romanian = flattenTranslations($romanian);

        expect(array_keys($romanian))->toBe(array_keys($english));

        foreach ($english as $key => $translation) {
            expect(translationPlaceholders($romanian[$key]))
                ->toBe(translationPlaceholders($translation), "Placeholder mismatch for [{$catalogue}.{$key}].");
        }
    }
});
