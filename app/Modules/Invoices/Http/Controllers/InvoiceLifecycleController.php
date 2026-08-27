<?php

namespace App\Modules\Invoices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Invoices\Actions\CancelInvoice;
use App\Modules\Invoices\Actions\IssueInvoice;
use App\Modules\Invoices\Actions\ReopenInvoice;
use App\Modules\Invoices\Exceptions\InvoiceLifecycleException;
use App\Modules\Invoices\Http\Requests\CancelInvoiceRequest;
use App\Modules\Invoices\Http\Requests\IssueInvoiceRequest;
use App\Modules\Invoices\Http\Requests\ReopenInvoiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class InvoiceLifecycleController extends Controller
{
    public function issue(
        IssueInvoiceRequest $request,
        Company $company,
        string $invoice,
        IssueInvoice $issue,
    ): RedirectResponse {
        try {
            $issue->handle($company, $request->user(), $invoice, $request->editVersion());
        } catch (InvoiceLifecycleException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('invoices_ui.feedback.issued'));
    }

    public function cancel(
        CancelInvoiceRequest $request,
        Company $company,
        string $invoice,
        CancelInvoice $cancel,
    ): RedirectResponse {
        try {
            $cancel->handle(
                $company,
                $request->user(),
                $invoice,
                $request->editVersion(),
                $request->boolean('confirmed'),
            );
        } catch (InvoiceLifecycleException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('invoices_ui.feedback.cancelled'));
    }

    public function reopen(
        ReopenInvoiceRequest $request,
        Company $company,
        string $invoice,
        ReopenInvoice $reopen,
    ): RedirectResponse {
        try {
            $reopen->handle($company, $request->user(), $invoice, $request->change());
        } catch (InvoiceLifecycleException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('invoices_ui.feedback.reopened'));
    }

    private function validationError(InvoiceLifecycleException $exception): never
    {
        $field = match ($exception->reason()) {
            'stale' => 'edit_version',
            'lifecycle_confirmation_required' => 'confirmed',
            'lifecycle_reason_invalid' => 'reason',
            default => 'invoice',
        };

        throw ValidationException::withMessages([
            $field => __("invoices_ui.errors.{$exception->reason()}"),
        ]);
    }
}
