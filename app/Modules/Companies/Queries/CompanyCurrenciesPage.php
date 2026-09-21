<?php

namespace App\Modules\Companies\Queries;

use App\Models\User;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\CurrencyCode;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Companies\Policies\CompanyAuthorization;
use App\Modules\Customers\Models\Customer;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class CompanyCurrenciesPage
{
    public function __construct(private CompanyAuthorization $authorization) {}

    /** @return array{currencies: list<array<string, mixed>>, currencyOptions: list<array{value: string, label: string, testId: string}>} */
    public function for(Company $company, User $actor): array
    {
        $membership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', $actor->id)
            ->first();

        if ($membership === null || ! $this->authorization->allows(
            $membership->role,
            CompanyAbility::ManageCompanySettings,
        )) {
            throw new AuthorizationException;
        }

        $currencies = CompanyCurrency::query()
            ->select('company_currencies.*')
            ->addSelect([
                'customer_reference_count' => Customer::query()->selectRaw('count(*)')
                    ->whereColumn('customers.currency_id', 'company_currencies.id'),
                'product_reference_count' => ProductService::query()->selectRaw('count(*)')
                    ->whereColumn('products_services.currency_id', 'company_currencies.id'),
            ])
            ->orderByDesc('active')
            ->orderByDesc('is_default')
            ->orderBy('currency_code')
            ->get();
        $existingCodes = $currencies->pluck('currency_code')->all();

        $rows = $currencies
            ->map(fn (CompanyCurrency $currency): array => $this->row($company, $currency))
            ->all();
        $options = collect(CurrencyCode::all())
            ->reject(fn (string $code): bool => in_array($code, $existingCodes, true))
            ->map(fn (string $code): array => [
                'value' => $code,
                'label' => $code,
                'testId' => "currency-option-{$code}",
            ])
            ->all();

        return [
            'currencies' => array_values($rows),
            'currencyOptions' => array_values($options),
        ];
    }

    /** @return array<string, mixed> */
    private function row(Company $company, CompanyCurrency $currency): array
    {
        $customerCount = (int) $currency->getAttribute('customer_reference_count');
        $productCount = (int) $currency->getAttribute('product_reference_count');

        return [
            'id' => $currency->id,
            'code' => $currency->currency_code,
            'precision' => $currency->currency_precision,
            'isDefault' => $currency->is_default,
            'active' => $currency->active,
            'updateUrl' => $currency->active
                ? route('company-currencies.update', [$company, $currency], false)
                : null,
            'defaultUrl' => $currency->active && ! $currency->is_default
                ? route('company-currencies.default', [$company, $currency], false)
                : null,
            'deactivateUrl' => $currency->active
                ? route('company-currencies.deactivate', [$company, $currency], false)
                : null,
            'restoreUrl' => ! $currency->active
                ? route('company-currencies.restore', [$company, $currency], false)
                : null,
            'deactivateGuard' => $this->deactivateGuard(
                $currency->is_default,
                $customerCount,
                $productCount,
            ),
        ];
    }

    /** @return array{blocked: bool, description: string|null} */
    private function deactivateGuard(bool $isDefault, int $customers, int $products): array
    {
        if ($isDefault) {
            return [
                'blocked' => true,
                'description' => __('companies_ui.settings.currencies.default_dependency_description'),
            ];
        }

        $blocked = $customers + $products > 0;

        return [
            'blocked' => $blocked,
            'description' => $blocked
                ? trans_choice('companies_ui.settings.currencies.source_dependency_description', 1, [
                    'customers' => $customers,
                    'products' => $products,
                ])
                : null,
        ];
    }
}
