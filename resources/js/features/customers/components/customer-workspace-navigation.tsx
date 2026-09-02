import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ResourceWorkspaceHeader } from '@/components/app/resource-workspace';
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
    actions?: ReactNode;
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
    actions,
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
        <ResourceWorkspaceHeader
            breadcrumbs={[
                { title: indexLabel, href: indexUrl },
                { title: customerName, href: overviewUrl },
            ]}
            title={customerName}
            description={description}
            status={
                <StatusBadge
                    status={archived ? 'archived' : 'active'}
                    label={
                        archived ? statusLabels.archived : statusLabels.active
                    }
                />
            }
            actions={actions}
            navigation={
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
            }
        />
    );
}
