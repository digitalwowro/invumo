import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { ActionLink } from '@/components/app/action-link';
import { Breadcrumbs } from '@/components/app/breadcrumbs';
import { PageSubtitle, PageTitle } from '@/components/app/typography';
import { StatusBadge } from '@/components/domain/status-badge';
import { cn } from '@/lib/utils';

type Props = {
    active: 'overview' | 'contacts' | 'defaults';
    customerName: string;
    archived: boolean;
    description: string;
    indexUrl: string;
    indexLabel: string;
    overviewUrl: string;
    contactsUrl: string;
    defaultsUrl: string;
    backLabel: string;
    statusLabels: { active: string; archived: string };
    label: string;
    labels: { overview: string; contacts: string; defaults: string };
};

export function CustomerWorkspaceNavigation({
    active,
    customerName,
    archived,
    description,
    indexUrl,
    indexLabel,
    overviewUrl,
    contactsUrl,
    defaultsUrl,
    backLabel,
    statusLabels,
    label,
    labels,
}: Props) {
    const items = [
        { value: 'overview', href: overviewUrl, label: labels.overview },
        { value: 'contacts', href: contactsUrl, label: labels.contacts },
        { value: 'defaults', href: defaultsUrl, label: labels.defaults },
    ] as const;

    return (
        <header
            data-slot="customer-workspace-header"
            className="-mx-4 -mt-6 border-b border-divider bg-background px-4 pt-5 sm:-mx-6 sm:px-6 md:sticky md:top-0 md:z-20 lg:-mx-8 lg:px-8"
        >
            <div className="flex flex-col gap-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 space-y-1">
                        <Breadcrumbs
                            breadcrumbs={[
                                { title: indexLabel, href: indexUrl },
                                { title: customerName, href: overviewUrl },
                            ]}
                        />
                        <div className="flex min-w-0 flex-wrap items-center gap-3">
                            <PageTitle>{customerName}</PageTitle>
                            <StatusBadge
                                status={archived ? 'archived' : 'active'}
                                label={
                                    archived
                                        ? statusLabels.archived
                                        : statusLabels.active
                                }
                            />
                        </div>
                        <PageSubtitle>{description}</PageSubtitle>
                    </div>
                    <ActionLink href={indexUrl} variant="secondary">
                        <ArrowLeft aria-hidden="true" />
                        {backLabel}
                    </ActionLink>
                </div>
                <nav aria-label={label} className="min-w-0 overflow-x-auto">
                    <div className="flex min-w-max items-center gap-0">
                        {items.map((item) => (
                            <Link
                                key={item.value}
                                href={item.href}
                                aria-current={
                                    active === item.value ? 'page' : undefined
                                }
                                className={cn(
                                    'flex-none border-b-2 px-3 py-2.5 text-sm font-semibold transition-colors',
                                    active === item.value
                                        ? 'border-foreground text-foreground'
                                        : 'border-transparent text-foreground-muted hover:text-foreground',
                                )}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </div>
                </nav>
            </div>
        </header>
    );
}
