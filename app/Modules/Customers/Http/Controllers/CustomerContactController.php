<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Actions\ArchiveCustomerContact;
use App\Modules\Customers\Actions\CreateCustomerContact;
use App\Modules\Customers\Actions\DeleteCustomerContact;
use App\Modules\Customers\Actions\RestoreCustomerContact;
use App\Modules\Customers\Actions\UpdateCustomerContact;
use App\Modules\Customers\Exceptions\CustomerContactException;
use App\Modules\Customers\Http\Requests\SaveCustomerContactRequest;
use App\Modules\Customers\Queries\CustomerContactsPage;
use App\Support\Inertia\CustomersUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerContactController extends Controller
{
    public function index(
        Request $request,
        Company $company,
        string $customer,
        CustomerContactsPage $page,
        CustomersUiTranslationBag $translations,
    ): Response {
        return Inertia::render('customers/contacts', [
            ...$page->for($company, $request->user(), $customer, app()->getLocale()),
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
        ]);
    }

    public function store(
        SaveCustomerContactRequest $request,
        Company $company,
        string $customer,
        CreateCustomerContact $create,
    ): RedirectResponse {
        try {
            $create->handle($company, $request->user(), $customer, $request->contact());
        } catch (CustomerContactException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('customers_ui.feedback.contact_created'));
    }

    public function update(
        SaveCustomerContactRequest $request,
        Company $company,
        string $customer,
        string $contact,
        UpdateCustomerContact $update,
    ): RedirectResponse {
        try {
            $update->handle($company, $request->user(), $customer, $contact, $request->contact());
        } catch (CustomerContactException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('customers_ui.feedback.contact_updated'));
    }

    public function archive(
        Request $request,
        Company $company,
        string $customer,
        string $contact,
        ArchiveCustomerContact $archive,
    ): RedirectResponse {
        try {
            $archive->handle($company, $request->user(), $customer, $contact);
        } catch (CustomerContactException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('customers_ui.feedback.contact_archived'));
    }

    public function restore(
        Request $request,
        Company $company,
        string $customer,
        string $contact,
        RestoreCustomerContact $restore,
    ): RedirectResponse {
        try {
            $restore->handle($company, $request->user(), $customer, $contact);
        } catch (CustomerContactException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('customers_ui.feedback.contact_restored'));
    }

    public function destroy(
        Request $request,
        Company $company,
        string $customer,
        string $contact,
        DeleteCustomerContact $delete,
    ): RedirectResponse {
        try {
            $delete->handle($company, $request->user(), $customer, $contact);
        } catch (CustomerContactException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('customers_ui.feedback.contact_deleted'));
    }

    private function validationError(CustomerContactException $exception): never
    {
        throw ValidationException::withMessages([
            'contact' => __("customers_ui.errors.{$exception->reason()}"),
        ]);
    }
}
