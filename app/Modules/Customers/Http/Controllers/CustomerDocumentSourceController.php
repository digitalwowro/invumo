<?php

namespace App\Modules\Customers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Http\Requests\CustomerDocumentSearchRequest;
use App\Modules\Customers\Queries\CustomerDocumentOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerDocumentSourceController extends Controller
{
    public function index(
        CustomerDocumentSearchRequest $request,
        Company $company,
        CustomerDocumentOptions $options,
    ): JsonResponse {
        return response()->json([
            'items' => $options->search($company, $request->user(), $request->search()),
        ]);
    }

    public function companyDefaults(
        Request $request,
        Company $company,
        CustomerDocumentOptions $options,
    ): JsonResponse {
        return response()->json($options->preview($company, $request->user(), null));
    }

    public function show(
        Request $request,
        Company $company,
        string $customer,
        CustomerDocumentOptions $options,
    ): JsonResponse {
        return response()->json($options->preview($company, $request->user(), $customer));
    }
}
