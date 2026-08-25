import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { FormSection } from '@/components/app/form-section';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import { emptyBankAccountFormData } from '@/features/companies/components/bank-account-form-data';
import { BankAccountFormFields } from '@/features/companies/components/bank-account-form-fields';
import type { CompanyOption } from '@/types/company';
import type {
    BankRoutingField,
    CompanyBankAccountTranslations,
} from '@/types/company-bank-account';

type Props = {
    storeUrl: string;
    currencyOptions: CompanyOption[];
    routingFields: BankRoutingField[];
    labels: CompanyBankAccountTranslations;
};

export function BankAccountCreateForm({
    storeUrl,
    currencyOptions,
    routingFields,
    labels,
}: Props) {
    const initial = () => emptyBankAccountFormData(routingFields);
    const [data, setData] = useState(initial);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const isDirty = JSON.stringify(data) !== JSON.stringify(initial());

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.post(storeUrl, data, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: setErrors,
            onSuccess: () => {
                setData(initial());
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
                        <SubmitButton processing={processing}>
                            {labels.create}
                        </SubmitButton>
                    </FormActions>
                }
            >
                <UnsavedChangesGuard
                    active={isDirty && !processing}
                    message={labels.unsaved_warning}
                />
                <BankAccountFormFields
                    data={data}
                    errors={errors}
                    currencyOptions={currencyOptions}
                    routingFields={routingFields}
                    labels={labels}
                    onChange={setData}
                />
            </FormSection>
        </form>
    );
}
