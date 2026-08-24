import { router } from '@inertiajs/react';
import { UserRoundCog } from 'lucide-react';
import { useId, useState } from 'react';
import type { FormEvent } from 'react';
import { PasswordField } from '@/components/app/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import {
    confirmPlatformPassword,
    hasRecentPlatformPasswordConfirmation,
    PlatformPasswordConfirmationError,
} from '@/features/platform/lib/platform-password-confirmation';
import { interpolate } from '@/lib/translations';
import type { PlatformTranslations } from '@/types';

export function PlatformImpersonateButton({
    url,
    targetName,
    confirmationStatusUrl,
    confirmationStoreUrl,
    translations,
}: {
    url: string;
    targetName: string;
    confirmationStatusUrl: string;
    confirmationStoreUrl: string;
    translations: PlatformTranslations;
}) {
    const formId = useId();
    const copy = translations.users;
    const common = translations.common;
    const [open, setOpen] = useState(false);
    const [password, setPassword] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string>();

    const reset = () => {
        setPassword('');
        setError(undefined);
    };

    const impersonate = () => {
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onStart: () => {
                    setProcessing(true);
                    setError(undefined);
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const begin = async () => {
        setProcessing(true);
        setError(undefined);

        try {
            if (
                await hasRecentPlatformPasswordConfirmation(
                    confirmationStatusUrl,
                )
            ) {
                impersonate();

                return;
            }
        } catch {
            // The confirmation POST remains the fail-closed authority when a
            // status probe is unavailable or malformed.
        }

        setProcessing(false);
        setOpen(true);
    };

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setProcessing(true);
        setError(undefined);

        try {
            await confirmPlatformPassword(confirmationStoreUrl, password);
            impersonate();
        } catch (failure) {
            setProcessing(false);
            setPassword('');
            setError(
                failure instanceof PlatformPasswordConfirmationError &&
                    failure.failure === 'incorrect'
                    ? copy.password_incorrect
                    : failure instanceof PlatformPasswordConfirmationError &&
                        failure.failure === 'rate-limited'
                      ? copy.password_rate_limited
                      : copy.password_unavailable,
            );
        }
    };

    return (
        <>
            <Button
                type="button"
                variant="secondary"
                data-testid="impersonation-trigger"
                disabled={processing}
                onClick={begin}
            >
                {processing ? <Spinner /> : <UserRoundCog aria-hidden="true" />}
                {copy.impersonate}
            </Button>
            <Dialog
                open={open}
                onOpenChange={(nextOpen) => {
                    if (processing && !nextOpen) {
                        return;
                    }

                    setOpen(nextOpen);

                    if (!nextOpen) {
                        reset();
                    }
                }}
            >
                <DialogContent closeLabel={common.close}>
                    <DialogHeader>
                        <DialogTitle>{copy.impersonate_title}</DialogTitle>
                        <DialogDescription>
                            {interpolate(copy.impersonate_description, {
                                user: targetName,
                            })}
                        </DialogDescription>
                    </DialogHeader>
                    <form id={formId} onSubmit={submit}>
                        <PasswordField
                            id={`${formId}-password`}
                            label={copy.password}
                            error={error}
                            input={{
                                name: 'password',
                                value: password,
                                autoComplete: 'current-password',
                                autoFocus: true,
                                required: true,
                                placeholder: copy.password_placeholder,
                                showLabel: copy.show_password,
                                hideLabel: copy.hide_password,
                                onChange: (event) =>
                                    setPassword(event.target.value),
                            }}
                        />
                    </form>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="secondary"
                                disabled={processing}
                            >
                                {common.cancel}
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            form={formId}
                            disabled={processing}
                            data-testid="impersonation-submit"
                        >
                            {processing && <Spinner />}
                            {copy.impersonate}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
