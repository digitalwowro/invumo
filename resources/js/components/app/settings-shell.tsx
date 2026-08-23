import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

type SettingsShellProps = {
    title: string;
    description: string;
    navigationLabel: string;
    items: NavItem[];
    children: ReactNode;
};

export function SettingsShell({
    title,
    description,
    navigationLabel,
    items,
    children,
}: SettingsShellProps) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <PageFrame width="default">
            <div className="space-y-8">
                <PageHeader title={title} subtitle={description} />
                <div className="flex flex-col gap-6 lg:flex-row lg:gap-12">
                    <aside className="w-full lg:w-48 lg:shrink-0">
                        <nav
                            className="flex flex-col gap-1"
                            aria-label={navigationLabel}
                        >
                            {items.map((item) => (
                                <Button
                                    key={toUrl(item.href)}
                                    size="sm"
                                    variant="ghost"
                                    asChild
                                    className={cn('w-full justify-start', {
                                        'bg-muted': isCurrentOrParentUrl(
                                            item.href,
                                        ),
                                    })}
                                >
                                    <Link href={item.href}>{item.title}</Link>
                                </Button>
                            ))}
                        </nav>
                    </aside>
                    <Separator className="lg:hidden" />
                    <section className="min-w-0 flex-1 space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </PageFrame>
    );
}
