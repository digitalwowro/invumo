import { Breadcrumbs } from '@/components/app/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useI18n } from '@/hooks/use-i18n';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { t } = useI18n();

    return (
        <header
            data-slot="mobile-sidebar-header"
            className="flex h-12 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-4 md:hidden"
        >
            <div className="flex items-center gap-2">
                <SidebarTrigger
                    className="-ml-1"
                    openLabel={t('accessibility.open_navigation')}
                    closeLabel={t('accessibility.close_navigation')}
                />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
        </header>
    );
}
