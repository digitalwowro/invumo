import { Form } from '@inertiajs/react';
import { CheckboxField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Stack } from '@/components/app/layout';
import { DestructiveFormDialog } from '@/components/app/responsive-dialog';
import { SelectField } from '@/components/app/select-field';
import { SystemMessage } from '@/components/app/system-message';
import type {
    CompanyMembersTranslations,
    CompanyOwnershipCandidate,
} from '@/types';

type Props = {
    transferUrl: string;
    candidates: CompanyOwnershipCandidate[];
    translations: CompanyMembersTranslations;
    cancelLabel: string;
    closeLabel: string;
};

const formId = 'company-ownership-transfer-form';

export function CompanyOwnershipTransfer({
    transferUrl,
    candidates,
    translations,
    cancelLabel,
    closeLabel,
}: Props) {
    return (
        <Form
            action={transferUrl}
            method="patch"
            id={formId}
            options={{ preserveScroll: true }}
        >
            {({ processing, errors, resetAndClearErrors }) => (
                <FormSection
                    title={translations.transfer_title}
                    description={translations.transfer_description}
                >
                    {candidates.length === 0 ? (
                        <SystemMessage
                            tone="warning"
                            title={translations.no_transfer_candidates_title}
                            description={
                                translations.no_transfer_candidates_description
                            }
                        />
                    ) : (
                        <DestructiveFormDialog
                            triggerLabel={translations.transfer_company}
                            title={translations.transfer_dialog_title}
                            description={
                                translations.transfer_dialog_description
                            }
                            cancelLabel={cancelLabel}
                            confirmLabel={translations.confirm_transfer}
                            closeLabel={closeLabel}
                            formId={formId}
                            processing={processing}
                            onDismiss={resetAndClearErrors}
                        >
                            <Stack gap="lg">
                                <input
                                    type="hidden"
                                    name="confirmed"
                                    value="1"
                                    form={formId}
                                />
                                <SelectField
                                    id="ownership-destination"
                                    form={formId}
                                    name="destination_membership_id"
                                    label={translations.transfer_destination}
                                    placeholder={
                                        translations.transfer_destination_placeholder
                                    }
                                    error={errors.destination_membership_id}
                                    required
                                    options={candidates.map((candidate) => ({
                                        value: candidate.id,
                                        label: `${candidate.name} · ${candidate.email} · ${translations.roles[candidate.role]}`,
                                    }))}
                                />
                                <input
                                    type="hidden"
                                    name="retain_former_owner"
                                    value="0"
                                    form={formId}
                                />
                                <CheckboxField
                                    id="retain-former-owner"
                                    label={translations.retain_former_owner}
                                    checkbox={{
                                        form: formId,
                                        name: 'retain_former_owner',
                                        value: '1',
                                        defaultChecked: true,
                                    }}
                                />
                                {errors.ownership && (
                                    <SystemMessage
                                        tone="error"
                                        title={errors.ownership}
                                    />
                                )}
                            </Stack>
                        </DestructiveFormDialog>
                    )}
                </FormSection>
            )}
        </Form>
    );
}
