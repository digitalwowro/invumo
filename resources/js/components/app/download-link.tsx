import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

type DownloadLinkProps = {
    children: ReactNode;
    href: string;
    testId?: string;
    variant?: 'primary' | 'secondary' | 'ghost';
};

export function DownloadLink({
    children,
    href,
    testId,
    variant = 'secondary',
}: DownloadLinkProps) {
    return (
        <Button asChild variant={variant}>
            <a href={href} download data-testid={testId}>
                {children}
            </a>
        </Button>
    );
}
