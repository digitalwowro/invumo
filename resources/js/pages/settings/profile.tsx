import { Form, Head, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { TextField } from '@/components/app/form-field';
import { FormSection } from '@/components/app/form-section';
import { Stack } from '@/components/app/layout';
import { SystemMessage } from '@/components/app/system-message';
import TextLink from '@/components/app/text-link';
import DeleteUser from '@/features/account/components/delete-user';
import { send } from '@/routes/verification';
import type { Auth, SettingsUiTranslations } from '@/types';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
    translations: SettingsUiTranslations;
};

export default function Profile({
    mustVerifyEmail,
    status,
    translations,
}: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const { page, shared } = translations;

    return (
        <>
            <Head title={page.headTitle} />

            <Stack gap="2xl">
                <Form
                    {...ProfileController.update.form()}
                    options={{ preserveScroll: true }}
                >
                    {({ processing, errors }) => (
                        <FormSection
                            title={page.title}
                            description={page.description}
                            actions={
                                <FormActions>
                                    <SubmitButton
                                        processing={processing}
                                        testId="update-profile-button"
                                    >
                                        {shared.save}
                                    </SubmitButton>
                                </FormActions>
                            }
                        >
                            <TextField
                                id="name"
                                label={page.name}
                                error={errors.name}
                                input={{
                                    name: 'name',
                                    defaultValue: auth.user.name,
                                    required: true,
                                    autoComplete: 'name',
                                    placeholder: page.namePlaceholder,
                                }}
                            />
                            <TextField
                                id="email"
                                label={page.email}
                                error={errors.email}
                                input={{
                                    type: 'email',
                                    name: 'email',
                                    defaultValue: auth.user.email,
                                    required: true,
                                    autoComplete: 'username',
                                    placeholder: page.emailPlaceholder,
                                }}
                            />

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <SystemMessage
                                        tone="warning"
                                        title={page.unverified}
                                        action={
                                            <TextLink href={send()} as="button">
                                                {page.resend}
                                            </TextLink>
                                        }
                                    />
                                )}

                            {status === 'verification-link-sent' && (
                                <SystemMessage title={page.verificationSent} />
                            )}
                        </FormSection>
                    )}
                </Form>

                <DeleteUser
                    translations={translations}
                    formId="delete-account-form"
                />
            </Stack>
        </>
    );
}
