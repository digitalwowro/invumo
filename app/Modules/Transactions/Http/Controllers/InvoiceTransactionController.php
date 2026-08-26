<?php

namespace App\Modules\Transactions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Transactions\Actions\CreateInvoiceTransaction;
use App\Modules\Transactions\Actions\DeleteInvoiceTransaction;
use App\Modules\Transactions\Actions\UpdateInvoiceTransaction;
use App\Modules\Transactions\Exceptions\InvoiceTransactionException;
use App\Modules\Transactions\Http\Requests\DeleteInvoiceTransactionRequest;
use App\Modules\Transactions\Http\Requests\SaveInvoiceTransactionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class InvoiceTransactionController extends Controller
{
    public function store(
        SaveInvoiceTransactionRequest $request,
        Company $company,
        string $invoice,
        CreateInvoiceTransaction $create,
    ): RedirectResponse {
        try {
            $create->handle($company, $request->user(), $invoice, $request->transaction());
        } catch (InvoiceTransactionException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('invoices_ui.feedback.transaction_created'));
    }

    public function update(
        SaveInvoiceTransactionRequest $request,
        Company $company,
        string $invoice,
        string $transaction,
        UpdateInvoiceTransaction $update,
    ): RedirectResponse {
        try {
            $update->handle(
                $company,
                $request->user(),
                $invoice,
                $transaction,
                $request->transaction(),
            );
        } catch (InvoiceTransactionException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('invoices_ui.feedback.transaction_updated'));
    }

    public function destroy(
        DeleteInvoiceTransactionRequest $request,
        Company $company,
        string $invoice,
        string $transaction,
        DeleteInvoiceTransaction $delete,
    ): RedirectResponse {
        try {
            $delete->handle(
                $company,
                $request->user(),
                $invoice,
                $transaction,
                (int) $request->validated('edit_version'),
                (string) $request->validated('mutation_key'),
                $request->boolean('confirmed'),
            );
        } catch (InvoiceTransactionException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('invoices_ui.feedback.transaction_deleted'));
    }

    private function validationError(InvoiceTransactionException $exception): never
    {
        $field = match ($exception->reason()) {
            'transaction_amount_invalid',
            'transaction_zero_total',
            'transaction_payment_exceeds_outstanding',
            'transaction_refund_exceeds_capacity',
            'transaction_adjustment_exceeds_balance' => 'amount',
            'transaction_future_date' => 'transaction_date',
            'transaction_stale' => 'edit_version',
            'transaction_confirmation_required' => 'confirmed',
            default => 'transaction',
        };

        throw ValidationException::withMessages([
            $field => __("invoices_ui.errors.{$exception->reason()}"),
        ]);
    }
}
