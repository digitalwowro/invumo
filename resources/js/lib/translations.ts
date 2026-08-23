import type { PluralMessages } from '@/types';

export type TranslationPath<T> = {
    [Key in Extract<keyof T, string>]: T[Key] extends string
        ? Key
        : T[Key] extends object
          ? `${Key}.${TranslationPath<T[Key]>}`
          : never;
}[Extract<keyof T, string>];

type ReplacementValue = number | string;
export type Replacements = Record<string, ReplacementValue>;

export function interpolate(
    message: string,
    replacements: Replacements = {},
): string {
    return message.replace(/:([A-Za-z_][A-Za-z0-9_]*)/g, (match, key) =>
        Object.hasOwn(replacements, key) ? String(replacements[key]) : match,
    );
}

export function translate<T extends object>(
    translations: T,
    path: TranslationPath<T>,
    replacements: Replacements = {},
): string {
    const message = String(path)
        .split('.')
        .reduce<unknown>((value, segment) => {
            if (
                typeof value !== 'object' ||
                value === null ||
                !(segment in value)
            ) {
                return undefined;
            }

            return (value as Record<string, unknown>)[segment];
        }, translations);

    return typeof message === 'string'
        ? interpolate(message, replacements)
        : String(path);
}

export function pluralize(
    locale: string,
    messages: PluralMessages,
    count: number,
    replacements: Replacements = {},
): string {
    const category = new Intl.PluralRules(locale).select(count);
    const message =
        messages[category as keyof PluralMessages] ?? messages.other;

    return interpolate(message, { ...replacements, count });
}
