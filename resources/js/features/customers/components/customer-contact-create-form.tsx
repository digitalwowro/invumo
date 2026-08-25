import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { FormSection } from '@/components/app/form-section';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import { emptyCustomerContact } from '@/features/customers/components/customer-contact-form-data';
import { CustomerContactFormFields } from '@/features/customers/components/customer-contact-form-fields';
import type {
    CustomerContactTranslations,
    CustomerFieldLimits,
} from '@/types/customer';

type Props = {
    storeUrl: string;
    limits: CustomerFieldLimits;
    labels: CustomerContactTranslations;
};

export function CustomerContactCreateForm({ storeUrl, limits, labels }: Props) {
    const [data, setData] = useState(emptyCustomerContact);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const isDirty =
        JSON.stringify(data) !== JSON.stringify(emptyCustomerContact());

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.post(storeUrl, data, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: setErrors,
            onSuccess: () => {
                setData(emptyCustomerContact());
                setErrors({});
            },
        });
    };

    return (
        <form onSubmit={submit}>
            <FormSection
                title={labels.create_title}
                description={labels.create_description}
                actions={
                    <FormActions>
                        <SubmitButton
                            processing={processing}
                            testId="customer-contact-create"
                        >
                            {labels.create}
                        </SubmitButton>
                    </FormActions>
                }
            >
                <UnsavedChangesGuard
                    active={isDirty && !processing}
                    message={labels.unsaved_warning}
                />
                <CustomerContactFormFields
                    data={data}
                    errors={errors}
                    limits={limits}
                    labels={labels}
                    onChange={setData}
                />
            </FormSection>
        </form>
    );
}
