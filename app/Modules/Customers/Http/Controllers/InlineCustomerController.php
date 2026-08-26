<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Actions\CreateCustomer;
use App\Modules\Customers\Http\Requests\SaveCustomerRequest;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use Illuminate\Http\RedirectResponse;

final class InlineCustomerController extends Controller
{
    public function __invoke(
        SaveCustomerRequest $request,
        Company $company,
        string $quote,
        CreateCustomer $create,
    ): RedirectResponse {
        Document::query()->whereKey($quote)->where('kind', DocumentKind::Quote)->firstOrFail();
        $customer = $create->handle($company, $request->user(), $request->customer());

        return back()->with('inline_customer_id', $customer->id);
    }
}
