import type { ReactNode } from 'react';

type PageFrameProps = {
    children: ReactNode;
    width?: 'default' | 'wide' | 'full';
};

const widthClasses = {
    default: 'max-w-5xl',
    wide: 'max-w-7xl',
    full: 'max-w-none',
} as const;

export function PageFrame({ children, width = 'wide' }: PageFrameProps) {
    return (
        <div
            data-slot="page-frame"
            className={`mx-auto w-full px-4 py-6 sm:px-6 lg:px-8 ${widthClasses[width]}`}
        >
            {children}
        </div>
    );
}
