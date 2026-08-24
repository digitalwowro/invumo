import { router, usePage } from '@inertiajs/react';
import { LogOut, UserRoundCog } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

export function ImpersonationBanner() {
    const { impersonation } = usePage().props;
    const [processing, setProcessing] = useState(false);

    if (!impersonation) {
        return null;
    }

    return (
        <aside
            data-slot="impersonation-banner"
            className="sticky top-0 z-50 flex h-14 w-full items-center gap-3 border-b border-warning-fill bg-background px-4 text-warning-text"
            role="status"
            aria-live="polite"
        >
            <UserRoundCog className="size-5 shrink-0" aria-hidden="true" />
            <p className="min-w-0 flex-1 truncate text-sm font-semibold">
                {impersonation.message}
            </p>
            <Button
                type="button"
                variant="secondary"
                disabled={processing}
                onClick={() =>
                    router.delete(impersonation.exitUrl, {
                        preserveScroll: false,
                        onStart: () => setProcessing(true),
                        onFinish: () => setProcessing(false),
                    })
                }
            >
                {processing ? <Spinner /> : <LogOut aria-hidden="true" />}
                {impersonation.exitLabel}
            </Button>
        </aside>
    );
}
