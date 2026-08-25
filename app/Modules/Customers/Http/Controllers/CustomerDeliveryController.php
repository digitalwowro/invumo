<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Actions\UpdateCustomerDelivery;
use App\Modules\Customers\Exceptions\CustomerDeliveryException;
use App\Modules\Customers\Http\Requests\UpdateCustomerDeliveryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class CustomerDeliveryController extends Controller
{
    public function update(
        UpdateCustomerDeliveryRequest $request,
        Company $company,
        string $customer,
        UpdateCustomerDelivery $update,
    ): RedirectResponse {
        try {
            $update->handle($company, $request->user(), $customer, $request->delivery());
        } catch (CustomerDeliveryException $exception) {
            throw ValidationException::withMessages([
                'delivery' => __("customers_ui.errors.{$exception->reason()}"),
            ]);
        }

        return back()->with('status', __('customers_ui.feedback.delivery_updated'));
    }
}
