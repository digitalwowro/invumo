import type { ReactNode } from 'react';

export function SubtleMessage({ children }: { children: ReactNode }) {
    return (
        <p className="rounded-md border border-divider bg-surface-subtle p-3 text-sm text-foreground-muted">
            {children}
        </p>
    );
}
