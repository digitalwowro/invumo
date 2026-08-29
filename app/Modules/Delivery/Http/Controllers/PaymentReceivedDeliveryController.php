<?php

namespace App\Modules\Delivery\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Actions\SendPaymentReceived;
use App\Modules\Delivery\Exceptions\DocumentDeliveryException;
use App\Modules\Delivery\Http\Requests\SendPaymentReceivedRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class PaymentReceivedDeliveryController extends Controller
{
    public function __invoke(
        SendPaymentReceivedRequest $request,
        Company $company,
        string $invoice,
        string $transaction,
        SendPaymentReceived $send,
    ): RedirectResponse {
        try {
            $send->handle(
                $company,
                $request->user(),
                $invoice,
                $transaction,
                $request->delivery(),
            );
        } catch (DocumentDeliveryException $exception) {
            throw ValidationException::withMessages([
                $exception->validationField() ?? 'delivery' => __(
                    "document_delivery.errors.{$exception->reason()}",
                ),
            ]);
        }

        return back()->with('status', __('document_delivery.feedback.payment_received_queued'));
    }
}
