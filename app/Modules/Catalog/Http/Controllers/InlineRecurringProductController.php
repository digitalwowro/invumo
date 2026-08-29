<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Actions\CreateProductService;
use App\Modules\Catalog\Exceptions\ProductServiceException;
use App\Modules\Catalog\Http\Requests\SaveProductServiceRequest;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Recurring\Data\RecurringTemplateState;
use App\Modules\Recurring\Models\RecurringTemplate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class InlineRecurringProductController extends Controller
{
    public function __invoke(
        SaveProductServiceRequest $request,
        Company $company,
        string $template,
        CreateProductService $create,
        CompanyAbilityCheck $abilities,
    ): RedirectResponse {
        RecurringTemplate::query()
            ->whereKey($template)
            ->where('state', RecurringTemplateState::Draft)
            ->firstOrFail();

        if (! $abilities->allows(
            $request->user(), $company, CompanyAbility::ManageRecurringDrafts,
        )) {
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
