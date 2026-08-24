import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    ClipboardList,
    Gauge,
    ReceiptText,
    Users,
    WalletCards,
} from 'lucide-react';
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
import type { NavItem } from '@/types';

export function PlatformSidebar() {
    const { platformContext } = usePage().props;

    if (!platformContext) {
        return null;
    }

    const items: NavItem[] = [
        {
            title: platformContext.navigation.overview,
            href: platformContext.overviewUrl,
            icon: Gauge,
        },
        {
            title: platformContext.navigation.users,
            href: platformContext.routes.users,
            icon: Users,
        },
        {
            title: platformContext.navigation.accounts,
            href: platformContext.routes.accounts,
            icon: WalletCards,
        },
        {
            title: platformContext.navigation.companies,
            href: platformContext.routes.companies,
            icon: Building2,
        },
        {
            title: platformContext.navigation.planLifecycle,
            href: platformContext.routes.planLifecycle,
            icon: ReceiptText,
        },
        {
            title: platformContext.navigation.audit,
            href: platformContext.routes.audit,
            icon: ClipboardList,
        },
    ];

    return (
        <Sidebar
            collapsible="offcanvas"
            mobileTitle={platformContext.label}
            mobileDescription={platformContext.navigationDescription}
        >
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={platformContext.overviewUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={items} label={platformContext.label} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
