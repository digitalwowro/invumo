import { Form } from '@inertiajs/react';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Grid } from '@/components/app/layout';
import { SelectField } from '@/components/app/select-field';
import type { CompanyMembersTranslations } from '@/types';

type Props = {
    storeUrl: string;
    translations: CompanyMembersTranslations;
};

export function CompanyInvitationForm({ storeUrl, translations }: Props) {
    return (
        <Form action={storeUrl} method="post" resetOnSuccess>
            {({ processing, errors }) => (
                <FormSection
                    title={translations.invite_title}
                    description={translations.invite_description}
                    actions={
                        <FormActions>
                            <SubmitButton processing={processing}>
                                {translations.invite}
                            </SubmitButton>
                        </FormActions>
                    }
                >
                    <Grid columns={2} gap="lg">
                        <TextField
                            id="invitation-email"
                            label={translations.email}
                            error={errors.email}
                            input={{
                                type: 'email',
                                name: 'email',
                                required: true,
                                maxLength: 254,
                                autoComplete: 'email',
                                placeholder: translations.email_placeholder,
                            }}
                        />
                        <SelectField
                            id="invitation-role"
                            name="role"
                            label={translations.role}
                            error={errors.role}
                            required
                            defaultValue="MEMBER"
                            options={[
                                {
                                    value: 'MEMBER',
                                    label: translations.roles.MEMBER,
                                },
                                {
                                    value: 'ADMIN',
                                    label: translations.roles.ADMIN,
                                },
                            ]}
                        />
                    </Grid>
                </FormSection>
            )}
        </Form>
    );
}
