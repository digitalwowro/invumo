import { Link, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, Plus } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar-menu';
import { useI18n } from '@/hooks/use-i18n';

export function CompanySwitcher() {
    const { auth, companyContext } = usePage().props;
    const { t } = useI18n();
    const current = companyContext.current;

    if (!auth.user) {
        return null;
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton size="lg" variant="outline">
                            <Building2 aria-hidden="true" />
                            <span className="min-w-0 flex-1 truncate">
                                {current?.name ?? t('company.select')}
                            </span>
                            <ChevronsUpDown
                                aria-hidden="true"
                                className="ml-auto"
                            />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="w-64">
                        <DropdownMenuLabel>
                            {t('company.switch')}
                        </DropdownMenuLabel>
                        {companyContext.available.map((company) => (
                            <DropdownMenuItem key={company.id} asChild>
                                <Link href={company.dashboardUrl}>
                                    <Building2 aria-hidden="true" />
                                    <span className="min-w-0 flex-1 truncate">
                                        {company.name}
                                    </span>
                                    {company.id === current?.id ? (
                                        <Check aria-hidden="true" />
                                    ) : null}
                                </Link>
                            </DropdownMenuItem>
                        ))}
                        <DropdownMenuSeparator />
                        {companyContext.indexUrl ? (
                            <DropdownMenuItem asChild>
                                <Link href={companyContext.indexUrl}>
                                    <Building2 aria-hidden="true" />
                                    {t('company.manage')}
                                </Link>
                            </DropdownMenuItem>
                        ) : null}
                        {companyContext.createUrl ? (
                            <DropdownMenuItem asChild>
                                <Link href={companyContext.createUrl}>
                                    <Plus aria-hidden="true" />
                                    {t('company.create')}
                                </Link>
                            </DropdownMenuItem>
                        ) : null}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
