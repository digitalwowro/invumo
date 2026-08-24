import { router } from '@inertiajs/react';
import { useEffect } from 'react';

type Props = {
    active: boolean;
    message: string;
};

export function UnsavedChangesGuard({ active, message }: Props) {
    useEffect(() => {
        if (!active) {
            return;
        }

        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };
        const removeBeforeVisit = router.on('before', (event) => {
            if (event.detail.visit.method !== 'get') {
                return;
            }

            return window.confirm(message);
        });

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () => {
            removeBeforeVisit();
            window.removeEventListener('beforeunload', handleBeforeUnload);
        };
    }, [active, message]);

    return null;
}
