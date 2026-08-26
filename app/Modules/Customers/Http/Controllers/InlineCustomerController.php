<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Customers\Actions\CreateCustomer;
use App\Modules\Customers\Http\Requests\SaveCustomerRequest;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;

final class InlineCustomerController extends Controller
{
    public function __invoke(
        SaveCustomerRequest $request,
        Company $company,
        string $document,
        CreateCustomer $create,
        CompanyAbilityCheck $abilities,
    ): RedirectResponse {
        $editable = Document::query()
            ->whereKey($document)
            ->whereIn('kind', [DocumentKind::Quote, DocumentKind::Invoice])
            ->firstOrFail();

        if (! $abilities->allows($request->user(), $company, $editable->kind->manageAbility())) {
            throw new AuthorizationException;
        }

        $customer = $create->handle($company, $request->user(), $request->customer());

        return back()->with('inline_customer_id', $customer->id);
    }
}
