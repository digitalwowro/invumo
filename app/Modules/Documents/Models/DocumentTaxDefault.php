<?php

namespace App\Modules\Documents\Models;

use App\Foundation\Database\TenantOwnedModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $document_id
 * @property string|null $tax_preset_id
 * @property string $name
 * @property string $percentage
 */
#[Fillable(['document_id', 'tax_preset_id', 'name', 'percentage'])]
final class DocumentTaxDefault extends TenantOwnedModel
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['percentage' => 'decimal:6'];
    }
}
