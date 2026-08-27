<?php

namespace App\Modules\Transactions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Transactions\Http\Requests\CompanyTransactionListRequest;
use App\Modules\Transactions\Queries\CompanyTransactionListPage;
use App\Support\Inertia\TransactionsUiTranslationBag;
use Inertia\Inertia;
use Inertia\Response;

final class CompanyTransactionController extends Controller
{
    public function index(
        CompanyTransactionListRequest $request,
        Company $company,
        CompanyTransactionListPage $page,
        TransactionsUiTranslationBag $translations,
    ): Response {
        return Inertia::render('transactions/index', [
            ...$page->for($company, $request->user(), $request),
            'translations' => $translations->toArray(),
        ]);
    }
}
