<?php

namespace App\Modules\Invoices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Invoices\Actions\IssueInvoice;
use App\Modules\Invoices\Exceptions\InvoiceLifecycleException;
use App\Modules\Invoices\Http\Requests\IssueInvoiceRequest;
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
            $field = $exception->reason() === 'stale' ? 'edit_version' : 'invoice';

            throw ValidationException::withMessages([
                $field => __("invoices_ui.errors.{$exception->reason()}"),
            ]);
        }

        return back()->with('status', __('invoices_ui.feedback.issued'));
    }
}
