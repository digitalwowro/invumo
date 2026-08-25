import { resolveOutwardBrandTheme } from '@/domain/companies/outward-brand-theme';
import type { CompanyAppearanceTranslations } from '@/types/company-appearance';

type Props = {
    companyName: string;
    brandColor: string;
    logoUrl?: string;
    labels: CompanyAppearanceTranslations;
};

export function OutwardDocumentPreview({
    companyName,
    brandColor,
    logoUrl,
    labels,
}: Props) {
    const theme = resolveOutwardBrandTheme(brandColor);

    return (
        <div className="overflow-hidden rounded-lg border border-border bg-background">
            <div
                aria-hidden="true"
                className="h-2"
                style={{ backgroundColor: theme.accentColor }}
            />
            <div className="grid gap-8 p-6 sm:grid-cols-2">
                <div className="flex min-w-0 flex-col gap-5">
                    {logoUrl && (
                        <img
                            src={logoUrl}
                            alt=""
                            className="h-12 max-w-48 object-contain object-left"
                        />
                    )}
                    <div>
                        <p
                            className="font-semibold"
                            style={{ color: theme.textColor }}
                        >
                            {companyName}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {labels.preview_document}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            {labels.preview_bill_to}
                        </p>
                        <p className="font-medium">{labels.preview_customer}</p>
                    </div>
                </div>
                <div className="flex min-w-0 flex-col items-start gap-5 sm:items-end sm:text-right">
                    <span
                        className="rounded-md px-3 py-1 text-sm font-semibold"
                        style={{
                            backgroundColor: theme.accentColor,
                            color: theme.onAccentColor,
                        }}
                    >
                        {labels.preview_number}
                    </span>
                    <div
                        className="w-full border-t pt-4 sm:max-w-64"
                        style={{ borderColor: theme.ruleColor }}
                    >
                        <p className="text-sm text-muted-foreground">
                            {labels.preview_total}
                        </p>
                        <p className="font-mono text-xl font-semibold tabular-nums">
                            {labels.preview_amount}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
