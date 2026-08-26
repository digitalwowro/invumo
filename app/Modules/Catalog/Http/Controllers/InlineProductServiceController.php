<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Actions\CreateProductService;
use App\Modules\Catalog\Exceptions\ProductServiceException;
use App\Modules\Catalog\Http\Requests\SaveProductServiceRequest;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class InlineProductServiceController extends Controller
{
    public function __invoke(
        SaveProductServiceRequest $request,
        Company $company,
        string $document,
        CreateProductService $create,
        CompanyAbilityCheck $abilities,
    ): RedirectResponse {
        $editable = Document::query()
            ->whereKey($document)
            ->whereIn('kind', [DocumentKind::Quote, DocumentKind::Invoice])
            ->firstOrFail();

        if (! $abilities->allows($request->user(), $company, $editable->kind->manageAbility())) {
            throw new AuthorizationException;
        }

        try {
            $product = $create->handle($company, $request->user(), $request->product());
        } catch (ProductServiceException $exception) {
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

        return back()->with('inline_product_id', $product->id);
    }
}
