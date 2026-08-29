<?php

namespace App\Modules\Invoices\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Actions\DeleteInvoiceReminders;
use App\Modules\Documents\Actions\FinalizeDocumentDeletion;
use App\Modules\Documents\Contracts\DeletesDocumentResources;
use App\Modules\Invoices\Data\InvoiceDeletionData;
use App\Modules\Invoices\Data\InvoiceDeletionState;
use App\Modules\Invoices\Exceptions\InvoiceDeletionException;
use App\Modules\Quotes\Models\QuoteInvoiceLink;
use App\Modules\Recurring\Actions\DeleteGeneratedInvoiceOccurrence;
use App\Modules\Recurring\Queries\RecurringInvoiceSource;
use App\Modules\Transactions\Actions\LockInvoiceTransactionAggregate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class DeleteInvoice
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private LockInvoiceTransactionAggregate $lockAggregate,
        private DeletesDocumentResources $resources,
        private DeleteInvoiceReminders $reminders,
        private RecurringInvoiceSource $recurringSource,
        private DeleteGeneratedInvoiceOccurrence $recurringOccurrence,
        private FinalizeDocumentDeletion $finalizeDeletion,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $documentId,
        InvoiceDeletionData $data,
    ): void {
        try {
            $this->tenantContext->runForMember(
                $actor,
                $company->id,
                fn (): mixed => DB::connection(config('database.tenant_connection'))->transaction(
                    fn (): bool => $this->delete($company, $actor, $documentId, $data),
                    3,
                ),
            );
        } catch (QueryException $exception) {
            if (in_array(($exception->errorInfo[0] ?? null), ['23001', '23503'], true)) {
                throw InvoiceDeletionException::dependency();
            }

            throw $exception;
        }
    }

    private function delete(
        Company $company,
        User $actor,
        string $documentId,
        InvoiceDeletionData $data,
    ): bool {
        $this->authorizer->authorize($actor, $company, CompanyAbility::DeleteInvoices);
        $this->recurringSource->lock($documentId);
        $context = $this->lockAggregate->handle($documentId);
        $links = QuoteInvoiceLink::query()
            ->where('invoice_id', $documentId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $resources = $this->resources->lock($documentId);
        $state = new InvoiceDeletionState(
            $context->invoice->lifecycle,
            $context->transactions->count(),
            $links->count(),
            $resources->publicLinkCount,
            $resources->deliveryCount,
            $resources->submissionInFlightCount,
        );

        if (! hash_equals($state->version(), $data->stateVersion)) {
            throw InvoiceDeletionException::stale();
        }

        if ($state->transactionCount > 0) {
            throw InvoiceDeletionException::transactionDependency();
        }

        if ($state->quoteCount > 0) {
            throw InvoiceDeletionException::quoteDependency();
        }

        if ($resources->submissionInFlightCount > 0) {
            throw InvoiceDeletionException::deliveryInProgress();
        }

        if (! $data->confirmed) {
            throw InvoiceDeletionException::confirmationRequired();
        }

        $highRisk = $state->highRisk();

        if ($highRisk && ! $data->confirmedHighRisk) {
            throw InvoiceDeletionException::highRiskConfirmationRequired();
        }

        if ($highRisk && $data->confirmationNumber !== $context->document->rendered_number) {
            throw InvoiceDeletionException::numberConfirmationInvalid();
        }

        $this->reminders->handle($context->document->id);
        $this->recurringOccurrence->handle($actor, $context->document->id);
        $this->finalizeDeletion->handle(
            $company->id,
            $actor,
            $context->document,
            'company.invoice.deleted',
            'Invoice',
            AuditPayload::fromAllowedFields([
                'document_number' => $context->document->rendered_number,
                'lifecycle' => $context->invoice->lifecycle->value,
                'had_public_link_history' => $resources->publicLinkCount > 0,
                'had_delivery_history' => $resources->deliveryCount > 0,
            ], ['document_number', 'lifecycle', 'had_public_link_history', 'had_delivery_history']),
        );

        return true;
    }
}
