<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\CatalogDocumentSearchRequest;
use App\Modules\Catalog\Http\Requests\CatalogLineDefaultsRequest;
use App\Modules\Catalog\Queries\CatalogDocumentOptions;
use App\Modules\Catalog\Queries\CatalogLineDefaults;
use App\Modules\Companies\Models\Company;
use Illuminate\Http\JsonResponse;

final class CatalogDocumentSourceController extends Controller
{
    public function index(
        CatalogDocumentSearchRequest $request,
        Company $company,
        CatalogDocumentOptions $options,
    ): JsonResponse {
        return response()->json([
            'items' => $options->search($company, $request->user(), $request->search()),
        ]);
    }

    public function show(
        CatalogLineDefaultsRequest $request,
        Company $company,
        string $product,
        CatalogLineDefaults $defaults,
    ): JsonResponse {
        return response()->json($defaults->for(
            $company,
            $request->user(),
            $product,
            $request->currencyCode(),
        ));
    }
}
