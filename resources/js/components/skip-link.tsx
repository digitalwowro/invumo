import { useI18n } from '@/hooks/use-i18n';

export function SkipLink() {
    const { t } = useI18n();

    return (
        <a
            href="#main-content"
            className="sr-only rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-20 focus:ring-2 focus:ring-ring-inverse focus:ring-offset-2 focus:ring-offset-sidebar"
        >
            {t('accessibility.skip_to_content')}
        </a>
    );
}
