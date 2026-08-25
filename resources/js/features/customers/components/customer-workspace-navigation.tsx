import { Link } from '@inertiajs/react';
import { Cluster } from '@/components/app/layout';
import { Button } from '@/components/ui/button';

type Props = {
    active: 'overview' | 'contacts';
    overviewUrl: string;
    contactsUrl: string;
    label: string;
    labels: { overview: string; contacts: string };
};

export function CustomerWorkspaceNavigation({
    active,
    overviewUrl,
    contactsUrl,
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
            </Cluster>
        </nav>
    );
}
