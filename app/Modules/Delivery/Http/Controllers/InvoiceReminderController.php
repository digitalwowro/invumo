<?php

namespace App\Modules\Delivery\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Actions\RetryInvoiceReminder;
use App\Modules\Delivery\Actions\SaveDocumentReminderRules;
use App\Modules\Delivery\Exceptions\DocumentDeliveryException;
use App\Modules\Delivery\Http\Requests\RetryInvoiceReminderRequest;
use App\Modules\Delivery\Http\Requests\SaveReminderRulesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class InvoiceReminderController extends Controller
{
    public function update(
        SaveReminderRulesRequest $request,
        Company $company,
        string $invoice,
        SaveDocumentReminderRules $save,
    ): RedirectResponse {
        $save->handle(
            $company,
            $request->user(),
            $invoice,
            (int) $request->editVersion(),
            $request->reminderRules(),
        );

        return back()->with('status', __('invoices_ui.reminders.feedback.saved'));
    }

    public function retry(
        RetryInvoiceReminderRequest $request,
        Company $company,
        string $invoice,
        string $reminder,
        RetryInvoiceReminder $retry,
    ): RedirectResponse {
        try {
            $retry->handle($company, $request->user(), $invoice, $reminder);
        } catch (DocumentDeliveryException $exception) {
            throw ValidationException::withMessages([
                'reminder' => __("document_delivery.errors.{$exception->reason()}"),
            ]);
        }

        return back()->with('status', __('invoices_ui.reminders.feedback.retry_queued'));
    }
}
