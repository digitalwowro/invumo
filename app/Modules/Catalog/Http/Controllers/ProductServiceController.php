<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Actions\ArchiveProductService;
use App\Modules\Catalog\Actions\CreateProductService;
use App\Modules\Catalog\Actions\DeleteProductService;
use App\Modules\Catalog\Actions\RestoreProductService;
use App\Modules\Catalog\Actions\UpdateProductService;
use App\Modules\Catalog\Exceptions\ProductServiceException;
use App\Modules\Catalog\Http\Requests\ProductServiceListRequest;
use App\Modules\Catalog\Http\Requests\SaveProductServiceRequest;
use App\Modules\Catalog\Queries\ProductServiceListPage;
use App\Modules\Companies\Models\Company;
use App\Support\Inertia\CatalogUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ProductServiceController extends Controller
{
    public function index(
        ProductServiceListRequest $request,
        Company $company,
        ProductServiceListPage $page,
        CatalogUiTranslationBag $translations,
    ): Response {
        return Inertia::render('catalog/index', [
            ...$page->for($company, $request->user(), $request),
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
        ]);
    }

    public function store(
        SaveProductServiceRequest $request,
        Company $company,
        CreateProductService $create,
    ): RedirectResponse {
        try {
            $create->handle($company, $request->user(), $request->product());
        } catch (ProductServiceException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('catalog_ui.feedback.created'));
    }

    public function update(
        SaveProductServiceRequest $request,
        Company $company,
        string $productService,
        UpdateProductService $update,
    ): RedirectResponse {
        try {
            $update->handle($company, $request->user(), $productService, $request->product());
        } catch (ProductServiceException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('catalog_ui.feedback.updated'));
    }

    public function archive(Request $request, Company $company, string $productService, ArchiveProductService $archive): RedirectResponse
    {
        try {
            $archive->handle($company, $request->user(), $productService);
        } catch (ProductServiceException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('catalog_ui.feedback.archived'));
    }

    public function restore(Request $request, Company $company, string $productService, RestoreProductService $restore): RedirectResponse
    {
        try {
            $restore->handle($company, $request->user(), $productService);
        } catch (ProductServiceException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('catalog_ui.feedback.restored'));
    }

    public function destroy(Request $request, Company $company, string $productService, DeleteProductService $delete): RedirectResponse
    {
        try {
            $delete->handle($company, $request->user(), $productService);
        } catch (ProductServiceException $exception) {
            $this->validationError($exception);
        }

        return back()->with('status', __('catalog_ui.feedback.deleted'));
    }

    private function validationError(ProductServiceException $exception): never
    {
        $field = match ($exception->reason()) {
            'currency_unavailable' => 'currency_id',
            'tax_unavailable' => 'tax_preset_id',
            'price_invalid' => 'unit_price',
            default => 'product_service',
        };

        throw ValidationException::withMessages([
            $field => __("catalog_ui.errors.{$exception->reason()}"),
        ]);
    }
}
