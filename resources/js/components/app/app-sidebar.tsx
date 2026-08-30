import { usePage } from '@inertiajs/react';
import {
    Boxes,
    FileText,
    HandCoins,
    LayoutGrid,
    ReceiptText,
    Repeat2,
    Settings,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { AppSidebarBrand } from '@/components/app/app-sidebar-brand';
import { NavMain } from '@/components/app/nav-main';
import { NavUser } from '@/components/app/nav-user';
import { Sidebar } from '@/components/ui/sidebar';
import {
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar-layout';
import { useI18n } from '@/hooks/use-i18n';
import { index as companySettingsIndex } from '@/routes/company-settings';
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

    if (companyContext.current && companyContext.abilities.view_quotes) {
        mainNavItems.push({
            title: t('navigation.quotes'),
            href: companyContext.current.quotesUrl,
            icon: FileText,
        });
    }

    if (companyContext.current && companyContext.abilities.view_invoices) {
        mainNavItems.push({
            title: t('navigation.invoices'),
            href: companyContext.current.invoicesUrl,
            icon: ReceiptText,
        });
    }

    if (companyContext.current && companyContext.abilities.view_transactions) {
        mainNavItems.push({
            title: t('navigation.transactions'),
            href: companyContext.current.transactionsUrl,
            icon: HandCoins,
        });
    }

    if (companyContext.current && companyContext.abilities.view_customers) {
        mainNavItems.push({
            title: t('navigation.customers'),
            href: companyContext.current.customersUrl,
            icon: Users,
        });
    }

    if (
        companyContext.current &&
        companyContext.abilities.view_recurring_templates
    ) {
        mainNavItems.push({
            title: t('navigation.recurring'),
            href: companyContext.automation?.failedRecurringCount
                ? companyContext.automation.failedRecurringUrl
                : companyContext.current.recurringUrl,
            icon: Repeat2,
            badge: companyContext.automation?.failedRecurringCount,
            badgeLabel: companyContext.automation?.failedRecurringCount
                ? t('accessibility.recurring_attention', {
                      count: companyContext.automation.failedRecurringCount,
                  })
                : undefined,
        });
    }

    if (companyContext.current && companyContext.abilities.manage_catalog) {
        mainNavItems.push({
            title: t('navigation.products'),
            href: companyContext.current.catalogUrl,
            icon: Boxes,
        });
    }

    if (
        !companyContext.current ||
        companyContext.abilities.manage_company_settings
    ) {
        mainNavItems.push({
            title: t('navigation.settings'),
            href: companyContext.current?.settingsUrl ?? editProfile(),
            activeHref: companyContext.current
                ? companySettingsIndex(companyContext.current.id)
                : '/settings',
            icon: Settings,
        });
    }

    return (
        <Sidebar
            collapsible="icon"
            mobileTitle={t('accessibility.navigation')}
            mobileDescription={t('accessibility.navigation_description')}
        >
            <SidebarHeader>
                <AppSidebarBrand href={homeUrl} />
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
