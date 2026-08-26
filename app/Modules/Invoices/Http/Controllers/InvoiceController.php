<?php

namespace App\Modules\Invoices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Invoices\Http\Requests\InvoiceListRequest;
use App\Modules\Invoices\Queries\InvoiceListPage;
use App\Support\Inertia\InvoicesUiTranslationBag;
use Inertia\Inertia;
use Inertia\Response;

final class InvoiceController extends Controller
{
    public function index(
        InvoiceListRequest $request,
        Company $company,
        InvoiceListPage $page,
        InvoicesUiTranslationBag $translations,
    ): Response {
        return Inertia::render('invoices/index', [
            ...$page->for($company, $request->user(), $request),
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
        ]);
    }
}
