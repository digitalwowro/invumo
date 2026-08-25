import { router } from '@inertiajs/react';
import { useId, useState } from 'react';
import type { FormEvent } from 'react';
import { SystemMessage } from '@/components/app/system-message';
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
import { FieldGroup } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import { customerContactData } from '@/features/customers/components/customer-contact-form-data';
import { CustomerContactFormFields } from '@/features/customers/components/customer-contact-form-fields';
import type {
    CustomerContact,
    CustomerContactTranslations,
    CustomerFieldLimits,
} from '@/types/customer';

type Props = {
    contact: CustomerContact;
    limits: CustomerFieldLimits;
    labels: CustomerContactTranslations;
    cancelLabel: string;
    closeLabel: string;
};

export function CustomerContactEditDialog({
    contact,
    limits,
    labels,
    cancelLabel,
    closeLabel,
}: Props) {
    const current = () => customerContactData(contact);
    const formId = useId();
    const [open, setOpen] = useState(false);
    const [data, setData] = useState(current);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const isDirty = JSON.stringify(data) !== JSON.stringify(current());

    const changeOpen = (nextOpen: boolean) => {
        if (
            !nextOpen &&
            isDirty &&
            !processing &&
            !window.confirm(labels.unsaved_warning)
        ) {
            return;
        }

        if (nextOpen) {
            setData(current());
            setErrors({});
        }

        setOpen(nextOpen);
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!contact.updateUrl) {
            return;
        }

        router.patch(contact.updateUrl, data, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: setErrors,
            onSuccess: () => {
                setOpen(false);
                setErrors({});
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogTrigger asChild>
                <Button type="button" variant="secondary">
                    {labels.edit}
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-2xl" closeLabel={closeLabel}>
                <DialogHeader>
                    <DialogTitle>{labels.edit_title}</DialogTitle>
                    <DialogDescription>
                        {labels.edit_description}
                    </DialogDescription>
                </DialogHeader>
                <form id={formId} onSubmit={submit}>
                    <FieldGroup>
                        {errors.contact && (
                            <SystemMessage
                                title={errors.contact}
                                tone="error"
                            />
                        )}
                        <CustomerContactFormFields
                            data={data}
                            errors={errors}
                            limits={limits}
                            labels={labels}
                            onChange={setData}
                        />
                    </FieldGroup>
                </form>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            {cancelLabel}
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        form={formId}
                        disabled={processing || !isDirty}
                    >
                        {processing && <Spinner />}
                        {labels.save}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
