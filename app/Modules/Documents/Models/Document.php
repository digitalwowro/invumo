<?php

namespace App\Modules\Documents\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Documents\Data\DocumentAssignmentSource;
use App\Modules\Documents\Data\DocumentKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $company_id
 * @property DocumentKind $kind
 * @property string $rendered_number
 * @property DocumentAssignmentSource $assignment_source
 * @property string|null $number_series_id
 * @property string|null $number_period_key
 * @property int|null $number_sequence
 * @property string $client_creation_key
 * @property CarbonImmutable|null $issue_date
 * @property string|null $currency_code
 * @property int<0, 8>|null $currency_precision
 * @property string|null $document_language
 * @property string $subtotal
 * @property string $tax_total
 * @property string $total
 * @property int $edit_version
 * @property int $content_version
 */
#[Fillable([
    'kind', 'customer_id', 'rendered_number', 'assignment_source',
    'number_series_id', 'number_period_key', 'number_sequence',
    'client_creation_key', 'issue_date', 'currency_code',
    'currency_precision', 'document_language', 'subtotal', 'tax_total',
    'total', 'edit_version', 'content_version',
])]
class Document extends TenantOwnedModel
{
    /** @return HasMany<DocumentLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(DocumentLine::class)->orderBy('position');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => DocumentKind::class,
            'assignment_source' => DocumentAssignmentSource::class,
            'number_sequence' => 'integer',
            'issue_date' => 'immutable_date',
            'currency_precision' => 'integer',
            'edit_version' => 'integer',
            'content_version' => 'integer',
        ];
    }
}
