<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Customers\Actions\CreateCustomer;
use App\Modules\Customers\Http\Requests\SaveCustomerRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;

final class InlineRecurringCustomerController extends Controller
{
    public function __invoke(
        SaveCustomerRequest $request,
        Company $company,
        CreateCustomer $create,
        CompanyAbilityCheck $abilities,
    ): RedirectResponse {
        if (! $abilities->allows(
            $request->user(), $company, CompanyAbility::ManageRecurringDrafts,
        )) {
            throw new AuthorizationException;
        }

        $customer = $create->handle($company, $request->user(), $request->customer());

        return back()->with('inline_customer_id', $customer->id);
    }
}
