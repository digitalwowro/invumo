import type { ReactNode } from 'react';
import { Stack } from '@/components/app/layout';

type Props = {
    children: ReactNode;
    aside?: ReactNode;
};

export function DocumentSourceLayout({ children, aside }: Props) {
    if (!aside) {
        return children;
    }

    return (
        <div className="grid min-w-0 items-stretch gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <Stack gap="xl">{children}</Stack>
            <aside className="min-h-full">{aside}</aside>
        </div>
    );
}
