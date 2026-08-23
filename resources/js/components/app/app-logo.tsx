import { usePage } from '@inertiajs/react';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div
                aria-hidden="true"
                className="flex size-8 items-center justify-center rounded-md border border-sidebar-border bg-sidebar-surface text-sm font-bold text-sidebar-foreground"
            >
                I
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {name}
                </span>
            </div>
        </>
    );
}
