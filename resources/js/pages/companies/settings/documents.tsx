import { Head } from '@inertiajs/react';
import { Stack } from '@/components/app/layout';
import { SectionHeader } from '@/components/app/section-header';
import { SystemMessage } from '@/components/app/system-message';
import { CompanyDocumentDefaultsForm } from '@/features/companies/components/company-document-defaults-form';
import type { CompaniesUiTranslations, CompanyOption } from '@/types/company';
import type {
    CompanyDocumentDefaults,
    CompanyDocumentLimits,
} from '@/types/company-document-defaults';

type Props = {
    documentDefaults: CompanyDocumentDefaults;
    documentLimits: CompanyDocumentLimits;
    languageOptions: CompanyOption[];
    emailAttachmentModeOptions: CompanyOption[];
    updateUrl: string;
    status?: string;
    translations: CompaniesUiTranslations;
};

export default function CompanyDocuments({
    documentDefaults,
    documentLimits,
    languageOptions,
    emailAttachmentModeOptions,
    updateUrl,
    status,
    translations,
}: Props) {
    const labels = translations.settings.documents;

    return (
        <>
            <Head title={labels.head_title} />
            <Stack gap="2xl">
                <SectionHeader
                    title={labels.title}
                    description={labels.description}
                />
                {status && <SystemMessage title={status} tone="money" />}
                <CompanyDocumentDefaultsForm
                    defaults={documentDefaults}
                    limits={documentLimits}
                    languageOptions={languageOptions}
                    attachmentModeOptions={emailAttachmentModeOptions}
                    updateUrl={updateUrl}
                    labels={labels}
                />
            </Stack>
        </>
    );
}
