import { interpolate } from '@/lib/translations';
import type {
    CustomerDefaultSource,
    CustomerDefaultsTranslations,
    CustomerResolvedDefaults,
} from '@/types/customer-defaults';

export type CustomerDefaultSummary = {
    label: string;
    value: string;
    source: CustomerDefaultSource;
};

export function customerResolvedDefaultSummaries(
    resolved: CustomerResolvedDefaults,
    labels: CustomerDefaultsTranslations,
): CustomerDefaultSummary[] {
    const unavailable = labels.not_configured;

    return [
        {
            label: labels.resolved_fields.currency,
            value: resolved.currency
                ? interpolate(labels.resolved_currency, {
                      code: resolved.currency.code,
                      precision: resolved.currency.precision,
                  })
                : unavailable,
            source: resolved.currency?.source ?? 'UNRESOLVED',
        },
        {
            label: labels.resolved_fields.document_language,
            value: resolved.documentLanguage.value
                ? (labels.languages[resolved.documentLanguage.value] ??
                  resolved.documentLanguage.value)
                : unavailable,
            source: resolved.documentLanguage.source,
        },
        {
            label: labels.resolved_fields.payment_term_days,
            value: resolved.paymentTermDays.value
                ? interpolate(labels.resolved_payment_term, {
                      value: resolved.paymentTermDays.value,
                  })
                : unavailable,
            source: resolved.paymentTermDays.source,
        },
        {
            label: labels.resolved_fields.tax_preset,
            value: resolved.taxPreset
                ? interpolate(labels.resolved_tax, {
                      name: resolved.taxPreset.name,
                      percentage: resolved.taxPreset.percentage,
                  })
                : unavailable,
            source: resolved.taxPreset?.source ?? 'UNRESOLVED',
        },
        {
            label: labels.resolved_fields.email_attachment_mode,
            value: labels.modes[resolved.emailAttachmentMode.value],
            source: resolved.emailAttachmentMode.source,
        },
        {
            label: labels.resolved_fields.recipients,
            value: String(resolved.recipients.count),
            source: resolved.recipients.source,
        },
    ];
}
