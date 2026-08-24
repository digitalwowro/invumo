import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, Settings } from 'lucide-react';
import type { ReactNode } from 'react';
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
import { edit as editProfile } from '@/routes/profile';
import type { NavItem } from '@/types';

export function AppSidebar({
    companySwitcher,
}: {
    companySwitcher?: ReactNode;
}) {
    const { t } = useI18n();
    const { companyContext } = usePage().props;

    if (!companyContext) {
        return null;
    }

    const homeUrl =
        companyContext.current?.dashboardUrl ?? companyContext.landingUrl;
    const mainNavItems: NavItem[] = [];

    mainNavItems.push({
        title: t('navigation.dashboard'),
        href: homeUrl,
        icon: LayoutGrid,
    });

    if (
        !companyContext.current ||
        companyContext.abilities.manage_company_settings
    ) {
        mainNavItems.push({
            title: t('navigation.settings'),
            href: companyContext.current?.membersUrl ?? editProfile(),
            icon: Settings,
        });
    }

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
                            <Link href={homeUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                {companySwitcher}
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
