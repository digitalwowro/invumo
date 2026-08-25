import type { CompanyOption } from '@/types/company';

export type CompanyAppearance = {
    primaryBrandColor: string;
    logo: { name: string; previewUrl: string } | null;
};

export type CompanyAppearanceFormData = {
    primary_brand_color: string;
    logo: File | null;
    remove_logo: boolean;
};

export type CompanyAppearanceTranslations = {
    head_title: string;
    brand_title: string;
    brand_description: string;
    logo_title: string;
    logo_description: string;
    logo_current_name: string;
    logo_preview_alt: string;
    color_label: string;
    color_description: string;
    custom_color_label: string;
    color_picker_label: string;
    preset_label: string;
    preview_title: string;
    preview_description: string;
    preview_document: string;
    preview_number: string;
    preview_bill_to: string;
    preview_customer: string;
    preview_total: string;
    preview_amount: string;
    save: string;
    unsaved_warning: string;
    upload: {
        dropPrompt: string;
        choose: string;
        replace: string;
        remove: string;
        selected: string;
        uploading: string;
    };
    feedback: { saved: string };
    fields: Record<string, string>;
    presets: Record<string, string>;
};

export type CompanyBrandColorPreset = CompanyOption;
