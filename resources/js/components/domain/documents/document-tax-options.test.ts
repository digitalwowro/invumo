import { describe, expect, it } from 'vitest';
import {
    applyLineTaxSelection,
    DOCUMENT_DEFAULT_TAX,
    lineTaxOptions,
    NO_TAX,
    updateInheritedLineTaxes,
} from '@/components/domain/documents/document-tax-options';
import type { DocumentLineDraft } from '@/types/document';

const defaultTax = { id: 'tax-1', name: 'Standard', percentage: '21' };
const reduced = { value: 'tax-2', label: 'Reduced', percentage: '9' };
const line: DocumentLineDraft = {
    key: 'line-1',
    id: null,
    productServiceId: null,
    description: 'Service',
    itemPrice: '100',
    quantity: '1',
    unit: 'item',
    periodUnit: 'NONE',
    periodQuantity: '',
    discountPercentage: '0',
    taxName: defaultTax.name,
    taxPercentage: defaultTax.percentage,
    taxPresetId: defaultTax.id,
    finalLineTotal: null,
    usesDocumentTaxDefault: true,
};

describe('document tax options', () => {
    it('updates inheriting lines while preserving explicit overrides', () => {
        const explicit = applyLineTaxSelection(
            { ...line, key: 'line-2' },
            reduced.value,
            defaultTax,
            [reduced],
        );
        const next = updateInheritedLineTaxes([line, explicit], {
            id: 'tax-3',
            name: 'New standard',
            percentage: '20',
        });

        expect(next[0]).toMatchObject({
            taxPresetId: 'tax-3',
            taxName: 'New standard',
            taxPercentage: '20',
        });
        expect(next[1]).toMatchObject({
            taxPresetId: 'tax-2',
            taxName: 'Reduced',
            taxPercentage: '9',
        });
    });

    it('can return an overridden line to the document default', () => {
        const explicit = applyLineTaxSelection(
            line,
            reduced.value,
            defaultTax,
            [reduced],
        );

        expect(
            applyLineTaxSelection(explicit, DOCUMENT_DEFAULT_TAX, defaultTax, [
                reduced,
            ]),
        ).toMatchObject({
            taxPresetId: defaultTax.id,
            taxName: defaultTax.name,
            taxPercentage: defaultTax.percentage,
            usesDocumentTaxDefault: true,
        });
    });

    it('shows the inherited tax once without provenance copy', () => {
        expect(
            lineTaxOptions(
                line,
                defaultTax,
                [
                    {
                        value: defaultTax.id,
                        label: defaultTax.name,
                        percentage: defaultTax.percentage,
                    },
                    reduced,
                ],
                { noTax: 'No tax' },
            ),
        ).toEqual([
            { value: DOCUMENT_DEFAULT_TAX, label: 'Standard 21%' },
            { value: reduced.value, label: 'Reduced 9%' },
            { value: NO_TAX, label: 'No tax' },
        ]);
    });

    it('preserves recurring inheritance semantics', () => {
        const recurring = { ...line, taxMode: 'INHERIT_CUSTOMER' as const };
        const explicit = applyLineTaxSelection(
            recurring,
            reduced.value,
            defaultTax,
            [reduced],
        );

        expect(explicit).toMatchObject({
            taxMode: 'EXPLICIT',
            taxPresetId: reduced.value,
        });
        expect(
            applyLineTaxSelection(explicit, DOCUMENT_DEFAULT_TAX, defaultTax, [
                reduced,
            ]),
        ).toMatchObject({ taxMode: 'INHERIT_CUSTOMER', taxPresetId: null });
    });
});
