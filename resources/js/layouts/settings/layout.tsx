import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { SettingsShell } from '@/components/app/settings-shell';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem, SettingsUiTranslations } from '@/types';

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { translations } = usePage<{
        translations: SettingsUiTranslations;
    }>().props;
    const items: NavItem[] = [
        {
            title: translations.layout.profile,
            href: edit(),
        },
        {
            title: translations.layout.security,
            href: editSecurity(),
        },
    ];

    return (
        <SettingsShell
            title={translations.layout.title}
            description={translations.layout.description}
            navigationLabel={translations.layout.navigationLabel}
            items={items}
        >
            {children}
        </SettingsShell>
    );
}
