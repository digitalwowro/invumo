import { usePage } from '@inertiajs/react';
import { pluralize, translate } from '@/lib/translations';
import type { Replacements, TranslationPath } from '@/lib/translations';
import type { CommonTranslations } from '@/types';

export function useI18n() {
    const { i18n } = usePage().props;

    return {
        locale: i18n.locale,
        supportedLocales: i18n.supportedLocales,
        common: i18n.common,
        t: (
            path: TranslationPath<CommonTranslations>,
            replacements: Replacements = {},
        ) => translate(i18n.common, path, replacements),
        plural: (
            messages: CommonTranslations['counts']['invoices'],
            count: number,
            replacements: Replacements = {},
        ) => pluralize(i18n.locale, messages, count, replacements),
    };
}
