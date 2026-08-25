import { Link } from '@inertiajs/react';
import { Cluster } from '@/components/app/layout';
import { Button } from '@/components/ui/button';

type Props = {
    active: 'overview' | 'contacts' | 'defaults';
    overviewUrl: string;
    contactsUrl: string;
    defaultsUrl: string;
    label: string;
    labels: { overview: string; contacts: string; defaults: string };
};

export function CustomerWorkspaceNavigation({
    active,
    overviewUrl,
    contactsUrl,
    defaultsUrl,
    label,
    labels,
}: Props) {
    return (
        <nav aria-label={label}>
            <Cluster gap="sm">
                <Button
                    asChild
                    size="sm"
                    variant={active === 'overview' ? 'secondary' : 'ghost'}
                >
                    <Link
                        href={overviewUrl}
                        aria-current={
                            active === 'overview' ? 'page' : undefined
                        }
                    >
                        {labels.overview}
                    </Link>
                </Button>
                <Button
                    asChild
                    size="sm"
                    variant={active === 'contacts' ? 'secondary' : 'ghost'}
                >
                    <Link
                        href={contactsUrl}
                        aria-current={
                            active === 'contacts' ? 'page' : undefined
                        }
                    >
                        {labels.contacts}
                    </Link>
                </Button>
                <Button
                    asChild
                    size="sm"
                    variant={active === 'defaults' ? 'secondary' : 'ghost'}
                >
                    <Link
                        href={defaultsUrl}
                        aria-current={
                            active === 'defaults' ? 'page' : undefined
                        }
                    >
                        {labels.defaults}
                    </Link>
                </Button>
            </Cluster>
        </nav>
    );
}
