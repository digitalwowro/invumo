<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Actions\UpdateCustomerDefaults;
use App\Modules\Customers\Exceptions\CustomerDefaultsException;
use App\Modules\Customers\Http\Requests\UpdateCustomerDefaultsRequest;
use App\Modules\Customers\Queries\CustomerDefaultsPage;
use App\Support\Inertia\CustomersUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerDefaultsController extends Controller
{
    public function index(
        Request $request,
        Company $company,
        string $customer,
        CustomerDefaultsPage $page,
        CustomersUiTranslationBag $translations,
    ): Response {
        return Inertia::render('customers/defaults', [
            ...$page->for($company, $request->user(), $customer),
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
        ]);
    }

    public function update(
        UpdateCustomerDefaultsRequest $request,
        Company $company,
        string $customer,
        UpdateCustomerDefaults $update,
    ): RedirectResponse {
        try {
            $update->handle($company, $request->user(), $customer, $request->defaults());
        } catch (CustomerDefaultsException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('customers_ui.feedback.defaults_updated'));
    }

    private function validationError(CustomerDefaultsException $exception): never
    {
        $field = match ($exception->reason()) {
            'defaults_currency_unavailable' => 'currency_id',
            'defaults_language_unavailable' => 'document_language',
            'defaults_payment_term_invalid' => 'payment_term_days',
            'defaults_tax_preset_unavailable' => 'tax_preset_id',
            default => 'defaults',
        };

        throw ValidationException::withMessages([
            $field => __("customers_ui.errors.{$exception->reason()}"),
        ]);
    }
}
