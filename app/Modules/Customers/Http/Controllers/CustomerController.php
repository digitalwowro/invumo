<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Customers\Actions\ArchiveCustomer;
use App\Modules\Customers\Actions\CreateCustomer;
use App\Modules\Customers\Actions\DeleteCustomer;
use App\Modules\Customers\Actions\RestoreCustomer;
use App\Modules\Customers\Actions\UpdateCustomer;
use App\Modules\Customers\Exceptions\CustomerException;
use App\Modules\Customers\Http\Requests\CustomerListRequest;
use App\Modules\Customers\Http\Requests\SaveCustomerRequest;
use App\Modules\Customers\Queries\CustomerFormOptions;
use App\Modules\Customers\Queries\CustomerListPage;
use App\Modules\Customers\Queries\CustomerWorkspacePage;
use App\Support\Inertia\CustomersUiTranslationBag;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerController extends Controller
{
    public function index(
        CustomerListRequest $request,
        Company $company,
        CustomerListPage $page,
        CustomersUiTranslationBag $translations,
    ): Response {
        return Inertia::render('customers/index', [
            ...$page->for($company, $request->user(), $request, app()->getLocale()),
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
        ]);
    }

    public function create(
        Request $request,
        Company $company,
        CompanyAbilityCheck $abilities,
        CustomerFormOptions $options,
        CustomersUiTranslationBag $translations,
    ): Response {
        if (! $abilities->allows($request->user(), $company, CompanyAbility::ManageCustomers)) {
            throw new AuthorizationException;
        }

        return Inertia::render('customers/create', [
            ...$options->for(app()->getLocale()),
            'storeUrl' => route('customers.store', $company, false),
            'indexUrl' => route('customers.index', $company, false),
            'translations' => $translations->toArray(),
        ]);
    }

    public function store(
        SaveCustomerRequest $request,
        Company $company,
        CreateCustomer $create,
    ): RedirectResponse {
        $customer = $create->handle($company, $request->user(), $request->customer());

        return redirect()->route('customers.show', [$company, $customer])
            ->with('status', __('customers_ui.feedback.created'));
    }

    public function show(
        Request $request,
        Company $company,
        string $customer,
        CustomerWorkspacePage $page,
        CustomersUiTranslationBag $translations,
    ): Response {
        return Inertia::render('customers/show', [
            ...$page->for($company, $request->user(), $customer, app()->getLocale()),
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
        ]);
    }

    public function update(
        SaveCustomerRequest $request,
        Company $company,
        string $customer,
        UpdateCustomer $update,
    ): RedirectResponse {
        try {
            $update->handle($company, $request->user(), $customer, $request->customer());
        } catch (CustomerException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('customers_ui.feedback.updated'));
    }

    public function archive(Request $request, Company $company, string $customer, ArchiveCustomer $archive): RedirectResponse
    {
        try {
            $archive->handle($company, $request->user(), $customer);
        } catch (CustomerException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('customers_ui.feedback.archived'));
    }

    public function restore(Request $request, Company $company, string $customer, RestoreCustomer $restore): RedirectResponse
    {
        try {
            $restore->handle($company, $request->user(), $customer);
        } catch (CustomerException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('customers_ui.feedback.restored'));
    }

    public function destroy(Request $request, Company $company, string $customer, DeleteCustomer $delete): RedirectResponse
    {
        try {
            $delete->handle($company, $request->user(), $customer);
        } catch (CustomerException $exception) {
            $this->validationError($exception);
        }

        return redirect()->route('customers.index', $company)
            ->with('status', __('customers_ui.feedback.deleted'));
    }

    private function validationError(CustomerException $exception): never
    {
        throw ValidationException::withMessages([
            'customer' => __("customers_ui.errors.{$exception->reason()}"),
        ]);
    }
}
