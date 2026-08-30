import { router } from '@inertiajs/react';
import { OperationalListPagination } from '@/components/app/operational-list-pagination';
import { invoiceListQuery } from '@/features/invoices/lib/invoice-list-query';
import type { InvoiceCursorPage, InvoiceFilters } from '@/types/invoice';
import type { OperationalListTranslations } from '@/types/localization';

type Props = {
    action: string;
    page: InvoiceCursorPage;
    filters: InvoiceFilters;
    commonLabels: OperationalListTranslations;
};

export function InvoicePagination({
    action,
    page,
    filters,
    commonLabels,
}: Props) {
    return (
        <OperationalListPagination
            shownCount={page.items.length}
            previousUrl={page.previousUrl}
            nextUrl={page.nextUrl}
            perPage={filters.perPage}
            onPerPageChange={(perPage) =>
                router.get(action, invoiceListQuery({ ...filters, perPage }), {
                    preserveScroll: true,
                    replace: true,
                })
            }
            labels={commonLabels}
        />
    );
}
