import { usePage } from '@inertiajs/react';
import {
    Building2,
    ClipboardList,
    Gauge,
    ReceiptText,
    Users,
    WalletCards,
} from 'lucide-react';
import { AppSidebarBrand } from '@/components/app/app-sidebar-brand';
import { NavMain } from '@/components/app/nav-main';
import { NavUser } from '@/components/app/nav-user';
import { Sidebar } from '@/components/ui/sidebar';
import {
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar-layout';
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
            collapsible="icon"
            mobileTitle={platformContext.label}
            mobileDescription={platformContext.navigationDescription}
        >
            <SidebarHeader>
                <AppSidebarBrand href={platformContext.overviewUrl} />
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
