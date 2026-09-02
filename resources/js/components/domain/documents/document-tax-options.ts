import type { CatalogTaxOption } from '@/types/catalog';
import type { DocumentLineDraft, DocumentTaxDefault } from '@/types/document';

export const DOCUMENT_DEFAULT_TAX = '__DOCUMENT_DEFAULT__';
export const NO_TAX = '__NO_TAX__';
const CURRENT_TAX = '__CURRENT_TAX__';

export function taxDefaultOptions(
    options: CatalogTaxOption[],
    current: DocumentTaxDefault | null,
    noTaxLabel: string,
) {
    const active = options.map(taxOption);

    if (current?.id && !options.some((option) => option.value === current.id)) {
        active.unshift({ value: current.id, label: taxLabel(current) });
    }

    return [{ value: NO_TAX, label: noTaxLabel }, ...active];
}

export function taxDefaultSelection(current: DocumentTaxDefault | null) {
    return current?.id ?? NO_TAX;
}

export function resolveTaxDefault(
    value: string,
    options: CatalogTaxOption[],
    current: DocumentTaxDefault | null,
): DocumentTaxDefault | null {
    if (value === NO_TAX) {
        return null;
    }

    const option = options.find((item) => item.value === value);

    if (option) {
        return {
            id: option.value,
            name: option.label,
            percentage: option.percentage,
        };
    }

    return current?.id === value ? current : null;
}

export function documentTaxDefaultChange(
    value: string,
    options: CatalogTaxOption[],
    current: DocumentTaxDefault | null,
) {
    const taxDefault = resolveTaxDefault(value, options, current);

    return {
        taxDefault,
        update<
            T extends {
                taxDefaultPresetId: string | null;
                lines: DocumentLineDraft[];
            },
        >(data: T): T {
            return {
                ...data,
                taxDefaultPresetId: taxDefault?.id ?? null,
                lines: updateInheritedLineTaxes(data.lines, taxDefault),
            };
        },
    };
}

export function initializeLineTaxInheritance(
    line: DocumentLineDraft,
    taxDefault: DocumentTaxDefault | null,
): DocumentLineDraft {
    if (line.taxMode !== undefined) {
        return line;
    }

    return {
        ...line,
        usesDocumentTaxDefault: sameTax(line, taxDefault),
    };
}

export function updateInheritedLineTaxes(
    lines: DocumentLineDraft[],
    taxDefault: DocumentTaxDefault | null,
) {
    return lines.map((line) => {
        if (line.taxMode === 'INHERIT_CUSTOMER') {
            return withTax(line, taxDefault, null);
        }

        return line.usesDocumentTaxDefault
            ? withTax(line, taxDefault, taxDefault?.id ?? null)
            : line;
    });
}

export function lineTaxOptions(
    line: DocumentLineDraft,
    taxDefault: DocumentTaxDefault | null,
    presets: CatalogTaxOption[],
    labels: { noTax: string },
) {
    const defaultLabel = taxLabel(taxDefault) || labels.noTax;
    const current = lineTaxSelection(line);
    const currentMatchesDefault =
        current !== DOCUMENT_DEFAULT_TAX &&
        (current === taxDefault?.id ||
            (current === NO_TAX && taxDefault === null));
    const options = [
        {
            value: currentMatchesDefault ? current : DOCUMENT_DEFAULT_TAX,
            label: defaultLabel,
        },
        ...presets
            .filter((preset) => preset.value !== taxDefault?.id)
            .map(taxOption),
        ...(taxDefault === null
            ? []
            : [{ value: NO_TAX, label: labels.noTax }]),
    ];

    if (current === CURRENT_TAX) {
        options.splice(1, 0, {
            value: CURRENT_TAX,
            label: lineTaxLabel(line),
        });
    } else if (
        line.taxPresetId &&
        !presets.some((option) => option.value === line.taxPresetId)
    ) {
        options.splice(1, 0, {
            value: line.taxPresetId,
            label: lineTaxLabel(line),
        });
    }

    return options;
}

export function lineTaxSelection(line: DocumentLineDraft) {
    if (line.taxMode === 'INHERIT_CUSTOMER' || line.usesDocumentTaxDefault) {
        return DOCUMENT_DEFAULT_TAX;
    }

    if (line.taxMode === 'NONE' || isNoTax(line)) {
        return NO_TAX;
    }

    return line.taxPresetId ?? CURRENT_TAX;
}

export function applyLineTaxSelection(
    line: DocumentLineDraft,
    value: string,
    taxDefault: DocumentTaxDefault | null,
    presets: CatalogTaxOption[],
): DocumentLineDraft {
    if (value === DOCUMENT_DEFAULT_TAX) {
        return {
            ...withTax(
                line,
                taxDefault,
                line.taxMode === undefined ? (taxDefault?.id ?? null) : null,
            ),
            taxMode:
                line.taxMode === undefined ? undefined : 'INHERIT_CUSTOMER',
            usesDocumentTaxDefault:
                line.taxMode === undefined ? true : undefined,
        };
    }

    if (value === NO_TAX) {
        return {
            ...withTax(line, null, null),
            taxMode: line.taxMode === undefined ? undefined : 'NONE',
            usesDocumentTaxDefault:
                line.taxMode === undefined ? false : undefined,
        };
    }

    const preset = presets.find((option) => option.value === value);

    if (!preset) {
        return line;
    }

    return {
        ...line,
        taxName: preset.label,
        taxPercentage: preset.percentage,
        taxPresetId: preset.value,
        taxMode: line.taxMode === undefined ? undefined : 'EXPLICIT',
        usesDocumentTaxDefault: line.taxMode === undefined ? false : undefined,
    };
}

function sameTax(
    line: Pick<DocumentLineDraft, 'taxPresetId' | 'taxName' | 'taxPercentage'>,
    taxDefault: DocumentTaxDefault | null,
) {
    if (taxDefault === null) {
        return isNoTax(line);
    }

    return (
        line.taxPresetId === taxDefault.id &&
        line.taxName === taxDefault.name &&
        Number(line.taxPercentage) === Number(taxDefault.percentage)
    );
}

function isNoTax(line: Pick<DocumentLineDraft, 'taxName' | 'taxPercentage'>) {
    return line.taxName === '' && Number(line.taxPercentage) === 0;
}

function withTax(
    line: DocumentLineDraft,
    tax: DocumentTaxDefault | null,
    taxPresetId: string | null,
): DocumentLineDraft {
    return {
        ...line,
        taxName: tax?.name ?? '',
        taxPercentage: tax?.percentage ?? '0',
        taxPresetId,
    };
}

function taxOption(option: CatalogTaxOption) {
    return {
        value: option.value,
        label: `${option.label} ${option.percentage}%`,
    };
}

function taxLabel(tax: Pick<DocumentTaxDefault, 'name' | 'percentage'> | null) {
    return tax ? `${tax.name} ${tax.percentage}%` : '';
}

function lineTaxLabel(
    line: Pick<DocumentLineDraft, 'taxName' | 'taxPercentage'>,
) {
    return line.taxName
        ? `${line.taxName} ${line.taxPercentage}%`
        : `${line.taxPercentage}%`;
}
