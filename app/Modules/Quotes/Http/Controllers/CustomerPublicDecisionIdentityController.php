<?php

namespace App\Modules\Quotes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Quotes\Actions\RedactCustomerPublicDecisionIdentity;
use App\Modules\Quotes\Exceptions\CustomerDecisionIdentityErasureException;
use App\Modules\Quotes\Http\Requests\RedactCustomerPublicDecisionIdentityRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class CustomerPublicDecisionIdentityController extends Controller
{
    public function destroy(
        RedactCustomerPublicDecisionIdentityRequest $request,
        Company $company,
        string $customer,
        RedactCustomerPublicDecisionIdentity $redact,
    ): RedirectResponse {
        try {
            $count = $redact->handle(
                $company,
                $request->user(),
                $customer,
                $request->erasure(),
            );
        } catch (CustomerDecisionIdentityErasureException $exception) {
            throw ValidationException::withMessages([
                'customer' => __("customers_ui.errors.public_decision_identity_{$exception->reason()}"),
            ]);
        }

        return back()->with('status', trans_choice(
            'customers_ui.feedback.public_decision_identity_redacted',
            $count,
            ['count' => $count],
        ));
    }
}
