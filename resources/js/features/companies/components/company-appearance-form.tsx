import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { FileUpload } from '@/components/app/file-upload';
import { FormActions, SaveButton } from '@/components/app/form-actions';
import { FormSection } from '@/components/app/form-section';
import { Stack } from '@/components/app/layout';
import { UnsavedChangesGuard } from '@/components/app/unsaved-changes-guard';
import { BrandColorField } from '@/components/domain/brand-color-field';
import { OutwardDocumentPreview } from '@/components/domain/outward-document-preview';
import {
    DEFAULT_OUTWARD_BRAND_COLOR,
    isOutwardBrandColor,
} from '@/domain/companies/outward-brand-theme';
import type {
    CompanyAppearance,
    CompanyAppearanceFormData,
    CompanyAppearanceTranslations,
    CompanyBrandColorPreset,
} from '@/types/company-appearance';

type Props = {
    companyName: string;
    appearance: CompanyAppearance;
    presets: CompanyBrandColorPreset[];
    updateUrl: string;
    labels: CompanyAppearanceTranslations;
};

export function CompanyAppearanceForm({
    companyName,
    appearance,
    presets,
    updateUrl,
    labels,
}: Props) {
    const [data, setData] = useState<CompanyAppearanceFormData>({
        primary_brand_color: appearance.primaryBrandColor,
        logo: null,
        remove_logo: false,
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const selectedPreviewUrl = useRef<string | undefined>(undefined);
    const [displayedSelectedPreviewUrl, setDisplayedSelectedPreviewUrl] =
        useState<string>();
    const isDirty =
        data.primary_brand_color !== appearance.primaryBrandColor ||
        data.logo !== null ||
        data.remove_logo;

    useEffect(() => {
        return () => {
            if (selectedPreviewUrl.current) {
                URL.revokeObjectURL(selectedPreviewUrl.current);
            }
        };
    }, []);

    const displayedLogoUrl = data.logo
        ? displayedSelectedPreviewUrl
        : data.remove_logo
          ? undefined
          : appearance.logo?.previewUrl;
    const previewColor = isOutwardBrandColor(data.primary_brand_color)
        ? data.primary_brand_color
        : DEFAULT_OUTWARD_BRAND_COLOR;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        let payload: FormData | Omit<CompanyAppearanceFormData, 'logo'>;

        if (data.logo !== null) {
            payload = new FormData();
            payload.append('primary_brand_color', data.primary_brand_color);
            payload.append('remove_logo', data.remove_logo ? '1' : '0');
            payload.append('logo', data.logo);
        } else {
            payload = {
                primary_brand_color: data.primary_brand_color,
                remove_logo: data.remove_logo,
            };
        }

        router.post(updateUrl, payload, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: setErrors,
            onSuccess: () => {
                clearSelectedPreview();
                setData((current) => ({
                    primary_brand_color: current.primary_brand_color,
                    logo: null,
                    remove_logo: false,
                }));
                setErrors({});
            },
        });
    }

    function selectLogo(logo: File | null) {
        clearSelectedPreview();
        selectedPreviewUrl.current = logo
            ? URL.createObjectURL(logo)
            : undefined;
        setDisplayedSelectedPreviewUrl(selectedPreviewUrl.current);
        setData((current) => ({
            ...current,
            logo,
            remove_logo: false,
        }));
    }

    function clearSelectedPreview() {
        if (selectedPreviewUrl.current) {
            URL.revokeObjectURL(selectedPreviewUrl.current);
            selectedPreviewUrl.current = undefined;
            setDisplayedSelectedPreviewUrl(undefined);
        }
    }

    return (
        <form onSubmit={submit}>
            <Stack gap="2xl">
                <UnsavedChangesGuard
                    active={isDirty && !processing}
                    message={labels.unsaved_warning}
                />
                <FormSection
                    title={labels.logo_title}
                    description={labels.logo_description}
                >
                    <FileUpload
                        id="company-logo"
                        name="logo"
                        label={labels.fields.logo}
                        labels={labels.upload}
                        value={data.logo}
                        existingFile={data.remove_logo ? null : appearance.logo}
                        selectedPreviewUrl={displayedSelectedPreviewUrl}
                        previewAlt={labels.logo_preview_alt}
                        accept="image/png,image/jpeg,image/webp"
                        error={errors.logo}
                        uploading={processing}
                        onChange={selectLogo}
                        onRemoveExisting={() =>
                            setData((current) => ({
                                ...current,
                                logo: null,
                                remove_logo: true,
                            }))
                        }
                    />
                </FormSection>
                <FormSection
                    title={labels.brand_title}
                    description={labels.brand_description}
                >
                    <BrandColorField
                        value={data.primary_brand_color}
                        presets={presets}
                        error={errors.primary_brand_color}
                        labels={labels}
                        onChange={(primary_brand_color) =>
                            setData((current) => ({
                                ...current,
                                primary_brand_color,
                            }))
                        }
                    />
                </FormSection>
                <FormSection
                    title={labels.preview_title}
                    description={labels.preview_description}
                >
                    <OutwardDocumentPreview
                        companyName={companyName}
                        brandColor={previewColor}
                        logoUrl={displayedLogoUrl}
                        labels={labels}
                    />
                </FormSection>
                <FormActions>
                    <SaveButton processing={processing} dirty={isDirty}>
                        {labels.save}
                    </SaveButton>
                </FormActions>
            </Stack>
        </form>
    );
}
