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
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { interpolate } from '@/lib/translations';
import type { PlatformTranslations } from '@/types';

export function PlatformImpersonateButton({
    url,
    targetName,
    translations,
}: {
    url: string;
    targetName: string;
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

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.post(
            url,
            { password },
            {
                preserveScroll: true,
                onStart: () => {
                    setProcessing(true);
                    setError(undefined);
                },
                onFinish: () => setProcessing(false),
                onError: (errors) => {
                    setError(errors.password);
                    setPassword('');
                },
            },
        );
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                setOpen(nextOpen);

                if (!nextOpen) {
                    reset();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="secondary"
                    data-testid="impersonation-trigger"
                >
                    <UserRoundCog aria-hidden="true" />
                    {copy.impersonate}
                </Button>
            </DialogTrigger>
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
                        <Button type="button" variant="secondary">
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
    );
}
