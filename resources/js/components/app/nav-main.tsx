import { Link } from '@inertiajs/react';
import { resolveActiveNavItem } from '@/components/app/resolve-active-nav-item';
import {
    SidebarGroup,
    SidebarGroupLabel,
} from '@/components/ui/sidebar-layout';
import {
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar-menu';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

export function NavMain({
    items = [],
    label,
}: {
    items: NavItem[];
    label?: string;
}) {
    const { currentUrl } = useCurrentUrl();
    const activeItem = resolveActiveNavItem(items, currentUrl);

    return (
        <SidebarGroup>
            {label ? <SidebarGroupLabel>{label}</SidebarGroupLabel> : null}
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={item === activeItem}
                            className="text-sidebar-nav"
                            tooltip={{ children: item.title }}
                        >
                            <Link href={item.href} prefetch>
                                {item.icon ? <item.icon /> : null}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                        {item.badge ? (
                            <SidebarMenuBadge aria-label={item.badgeLabel}>
                                {item.badge}
                            </SidebarMenuBadge>
                        ) : null}
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
