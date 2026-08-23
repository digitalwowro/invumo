import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

type ActionLinkProps = {
    children: ReactNode;
    href: NonNullable<InertiaLinkProps['href']>;
    variant?: 'primary' | 'secondary' | 'ghost';
};

export function ActionLink({
    children,
    href,
    variant = 'primary',
}: ActionLinkProps) {
    return (
        <Button asChild variant={variant}>
            <Link href={href}>{children}</Link>
        </Button>
    );
}
