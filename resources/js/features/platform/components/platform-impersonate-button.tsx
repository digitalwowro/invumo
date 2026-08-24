import { router } from '@inertiajs/react';
import { UserRoundCog } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

export function PlatformImpersonateButton({
    url,
    label,
}: {
    url: string;
    label: string;
}) {
    const [processing, setProcessing] = useState(false);

    return (
        <Button
            type="button"
            variant="secondary"
            disabled={processing}
            onClick={() =>
                router.post(
                    url,
                    {},
                    {
                        onStart: () => setProcessing(true),
                        onFinish: () => setProcessing(false),
                    },
                )
            }
        >
            {processing ? <Spinner /> : <UserRoundCog aria-hidden="true" />}
            {label}
        </Button>
    );
}
