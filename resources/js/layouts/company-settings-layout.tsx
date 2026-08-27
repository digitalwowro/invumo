import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { SettingsShell } from '@/components/app/settings-shell';
import type { CompaniesUiTranslations } from '@/types/company';
import type { CompanySettingsNavigationItem } from '@/types/company-settings';
import type { NavItem } from '@/types/navigation';

export default function CompanySettingsLayout({ children }: PropsWithChildren) {
    const { companySettingsNavigation, translations } = usePage<{
        companySettingsNavigation: CompanySettingsNavigationItem[];
        translations: CompaniesUiTranslations;
    }>().props;
    const labels = translations.settings.layout;
    const items: NavItem[] = companySettingsNavigation.map((item) => ({
        title: labels.navigation[item.key],
        href: item.href,
    }));

    return (
        <SettingsShell
            title={labels.title}
            description={labels.description}
            navigationLabel={labels.navigation_label}
            items={items}
        >
            {children}
        </SettingsShell>
    );
}
