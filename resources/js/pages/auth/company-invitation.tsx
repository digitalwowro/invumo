import { Form, Head } from '@inertiajs/react';
import { ActionLink } from '@/components/app/action-link';
import { FormActions, SubmitButton } from '@/components/app/form-actions';
import { Stack } from '@/components/app/layout';
import { SystemMessage } from '@/components/app/system-message';
import TextLink from '@/components/app/text-link';
import { Body, BodyStrong, SecondaryText } from '@/components/app/typography';
import { useI18n } from '@/hooks/use-i18n';
import { interpolate } from '@/lib/translations';
import { logout } from '@/routes';
import type {
    CompanyInvitationTranslations,
    CompanyInvitationView,
} from '@/types';

type Props = {
    invitation: CompanyInvitationView;
    acceptUrl: string;
    loginUrl: string;
    registerUrl: string;
    verificationUrl: string;
    translations: CompanyInvitationTranslations;
};

export default function CompanyInvitationPage({
    invitation,
    acceptUrl,
    loginUrl,
    registerUrl,
    verificationUrl,
    translations,
}: Props) {
    const labels = translations.page;
    const { locale } = useI18n();

    return (
        <>
            <Head title={labels.headTitle} />
            {invitation.available ? (
                <Stack gap="xl">
                    <Stack gap="sm">
                        <BodyStrong>
                            {interpolate(labels.invitedTo, {
                                company: invitation.companyName ?? '',
                                role: invitation.role
                                    ? labels.roles[invitation.role]
                                    : '',
                            })}
                        </BodyStrong>
                        <Body>
                            {interpolate(labels.sentTo, {
                                email: invitation.invitedEmail ?? '',
                            })}
                        </Body>
                        {invitation.expiresAt && (
                            <SecondaryText>
                                {interpolate(labels.expires, {
                                    date: new Intl.DateTimeFormat(locale, {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    }).format(new Date(invitation.expiresAt)),
                                })}
                            </SecondaryText>
                        )}
                    </Stack>
                    <InvitationAction
                        invitation={invitation}
                        acceptUrl={acceptUrl}
                        loginUrl={loginUrl}
                        registerUrl={registerUrl}
                        verificationUrl={verificationUrl}
                        translations={translations}
                    />
                </Stack>
            ) : (
                <SystemMessage
                    title={labels.unavailableTitle}
                    description={
                        labels[
                            invitation.status as Exclude<
                                CompanyInvitationView['status'],
                                'pending'
                            >
                        ]
                    }
                    tone="warning"
                />
            )}
        </>
    );
}

function InvitationAction({
    invitation,
    acceptUrl,
    loginUrl,
    registerUrl,
    verificationUrl,
    translations,
}: Omit<Props, 'translations'> & {
    translations: CompanyInvitationTranslations;
}) {
    const labels = translations.page;

    if (!invitation.authenticated) {
        return (
            <FormActions align="stretch">
                <ActionLink href={loginUrl}>{labels.signIn}</ActionLink>
                <ActionLink href={registerUrl} variant="secondary">
                    {labels.register}
                </ActionLink>
            </FormActions>
        );
    }

    if (!invitation.emailMatches) {
        return (
            <Stack gap="lg">
                <SystemMessage
                    title={interpolate(labels.wrongAccount, {
                        email: invitation.invitedEmail ?? '',
                    })}
                    tone="warning"
                />
                <TextLink href={logout()} as="button">
                    {labels.signIn}
                </TextLink>
            </Stack>
        );
    }

    if (!invitation.emailVerified) {
        return (
            <Stack gap="lg">
                <SystemMessage title={labels.verify} />
                <ActionLink href={verificationUrl}>
                    {labels.verifyAction}
                </ActionLink>
            </Stack>
        );
    }

    return (
        <Form action={acceptUrl} method="post">
            {({ processing, errors }) => (
                <Stack gap="lg">
                    {errors.invitation && (
                        <SystemMessage title={errors.invitation} tone="error" />
                    )}
                    <FormActions align="stretch">
                        <SubmitButton processing={processing}>
                            {labels.accept}
                        </SubmitButton>
                    </FormActions>
                </Stack>
            )}
        </Form>
    );
}
