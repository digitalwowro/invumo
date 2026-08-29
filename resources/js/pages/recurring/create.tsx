import type { Page } from '@inertiajs/core';
import { Head, useForm } from '@inertiajs/react';
import { FilePlus2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Stack } from '@/components/app/layout';
import { PageFrame } from '@/components/app/page-frame';
import { PageHeader } from '@/components/app/page-header';
import { SystemMessage } from '@/components/app/system-message';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import { DocumentCustomerControls } from '@/components/domain/documents/document-customer-controls';
import { DocumentCustomerSelector } from '@/components/domain/documents/document-customer-selector';
import { InlineDocumentCustomerDialog } from '@/components/domain/documents/inline-document-customer-dialog';
import type { CustomerTranslations } from '@/types/customer';
import type {
    DocumentCustomerFormOptions,
    DocumentCustomerSelection,
    DocumentSourceUrls,
} from '@/types/document';
import type { RecurringTranslations } from '@/types/recurring-translations';

type Props = {
    storeUrl: string;
    creationKey: string;
    sourceUrls: DocumentSourceUrls;
    inlineCustomerStoreUrl: string;
    inlineCreatedCustomer: DocumentCustomerSelection | null;
    sourceAbilities: { createCustomer: boolean };
    customerForm: DocumentCustomerFormOptions;
    limits: { internalName: number };
    translations: RecurringTranslations;
    customerTranslations: CustomerTranslations;
};

const emptyCustomer: DocumentCustomerSelection = {
    customerId: null,
    displayName: null,
    currencyCode: null,
    currencyPrecision: null,
    documentLanguage: null,
    paymentTermDays: null,
    taxDefault: null,
    emailAttachmentMode: 'SECURE_LINK_ONLY',
    recipientCount: 0,
    confirmationToken: null,
};

export default function CreateRecurringTemplate(props: Props) {
    const [customer, setCustomer] = useState(
        props.inlineCreatedCustomer ?? emptyCustomer,
    );
    const [selectorOpen, setSelectorOpen] = useState(false);
    const [creatorOpen, setCreatorOpen] = useState(false);
    const form = useForm({
        creationKey: props.creationKey,
        internalName: '',
        customerId: customer.customerId ?? '',
        customerConfirmationToken: customer.confirmationToken ?? '',
    });
    const labels = props.translations.editor;

    const applyCustomer = (selection: DocumentCustomerSelection) => {
        setCustomer(selection);
        form.setData((current) => ({
            ...current,
            customerId: selection.customerId ?? '',
            customerConfirmationToken: selection.confirmationToken ?? '',
        }));
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.transform((data) => ({
            creation_key: data.creationKey,
            internal_name: data.internalName,
            customer_id: data.customerId,
            customer_confirmation_token: data.customerConfirmationToken,
        }));
        form.post(props.storeUrl);
    };

    return (
        <>
            <Head title={props.translations.create.head_title} />
            <PageFrame width="full">
                <Stack gap="2xl">
                    <PageHeader
                        title={props.translations.create.title}
                        subtitle={props.translations.create.description}
                    />
                    <form onSubmit={submit}>
                        <Stack gap="xl">
                            <UnsavedChangesGuard
                                active={
                                    form.isDirty &&
                                    !form.processing &&
                                    !creatorOpen
                                }
                                message={labels.unsaved_warning}
                            />
                            <FormSection
                                title={props.translations.create.section_title}
                                description={
                                    props.translations.create
                                        .section_description
                                }
                            >
                                <TextField
                                    label={
                                        props.translations.create.internal_name
                                    }
                                    description={
                                        props.translations.create
                                            .internal_name_description
                                    }
                                    error={form.errors.internalName}
                                    input={{
                                        value: form.data.internalName,
                                        maxLength: props.limits.internalName,
                                        onChange: (event) =>
                                            form.setData(
                                                'internalName',
                                                event.target.value,
                                            ),
                                    }}
                                />
                            </FormSection>
                            <DocumentCustomerControls
                                customer={customer}
                                labels={labels}
                                onSelect={() => setSelectorOpen(true)}
                            />
                            {(form.errors.customerId ||
                                form.errors.customerConfirmationToken) && (
                                <SystemMessage
                                    title={
                                        form.errors.customerId ??
                                        form.errors.customerConfirmationToken ??
                                        ''
                                    }
                                    tone="error"
                                />
                            )}
                            <FormActions separated>
                                <SubmitButton
                                    processing={form.processing}
                                    disabled={customer.customerId === null}
                                    testId="create-recurring-template"
                                >
                                    <FilePlus2
                                        aria-hidden="true"
                                        data-icon="inline-start"
                                    />
                                    {props.translations.create.submit}
                                </SubmitButton>
                            </FormActions>
                        </Stack>
                    </form>
                </Stack>
            </PageFrame>
            <DocumentCustomerSelector
                open={selectorOpen}
                searchUrl={props.sourceUrls.customerSearch}
                companyDefaultsUrl={props.sourceUrls.companyCustomerDefaults}
                labels={labels}
                canCreate={props.sourceAbilities.createCustomer}
                allowCompanyDefaults={false}
                onOpenChange={setSelectorOpen}
                onCreate={() => {
                    setSelectorOpen(false);
                    setCreatorOpen(true);
                }}
                onSelect={applyCustomer}
            />
            <InlineDocumentCustomerDialog
                open={creatorOpen}
                storeUrl={props.inlineCustomerStoreUrl}
                options={props.customerForm}
                documentLabels={labels}
                customerLabels={props.customerTranslations}
                onOpenChange={setCreatorOpen}
                onCreated={(page: Page) => {
                    const created = page.props
                        .inlineCreatedCustomer as DocumentCustomerSelection | null;

                    if (created !== null) {
                        setCreatorOpen(false);
                        applyCustomer(created);
                    }
                }}
            />
        </>
    );
}
