<?php

namespace App\Modules\Recurring\Actions;

use App\Modules\Catalog\Models\ProductService;
use App\Modules\Recurring\Data\RecurringTemplateLineData;
use App\Modules\Recurring\Exceptions\RecurringTemplateException;
use Illuminate\Database\Eloquent\Collection;

final class LockRecurringTemplateProducts
{
    /**
     * @param  list<RecurringTemplateLineData>  $lines
     * @return Collection<int, ProductService>
     */
    public function handle(array $lines): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map(
            fn (RecurringTemplateLineData $line): ?string => $line->line->productServiceId,
            $lines,
        ))));
        sort($ids);
        $products = ProductService::query()
            ->whereKey($ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if (array_diff($ids, $products->modelKeys()) !== []) {
            throw RecurringTemplateException::sourceUnavailable();
        }

        return $products;
    }
}
