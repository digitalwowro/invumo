import { Link } from '@inertiajs/react';
import AppLogo from '@/components/app/app-logo';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar-menu';
import { useI18n } from '@/hooks/use-i18n';

export function AppSidebarBrand({ href }: { href: string }) {
    const { t } = useI18n();

    return (
        <div className="flex min-w-0 items-center gap-1">
            <SidebarMenu className="min-w-0 flex-1 group-data-[collapsible=icon]:hidden">
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" asChild>
                        <Link href={href} prefetch>
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarTrigger
                className="hidden shrink-0 md:inline-flex"
                openLabel={t('accessibility.open_navigation')}
                closeLabel={t('accessibility.close_navigation')}
            />
        </div>
    );
}
