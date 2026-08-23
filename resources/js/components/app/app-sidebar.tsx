import { Link } from '@inertiajs/react';
import { LayoutGrid } from 'lucide-react';
import AppLogo from '@/components/app/app-logo';
import { NavMain } from '@/components/app/nav-main';
import { NavUser } from '@/components/app/nav-user';
import { Sidebar } from '@/components/ui/sidebar';
import {
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar-layout';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar-menu';
import { useI18n } from '@/hooks/use-i18n';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { t } = useI18n();
    const mainNavItems: NavItem[] = [
        {
            title: t('navigation.dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    return (
        <Sidebar
            collapsible="offcanvas"
            mobileTitle={t('accessibility.navigation')}
            mobileDescription={t('accessibility.navigation_description')}
        >
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
