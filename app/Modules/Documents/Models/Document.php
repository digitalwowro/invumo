<?php

namespace App\Modules\Documents\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Documents\Data\DocumentAssignmentSource;
use App\Modules\Documents\Data\DocumentKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $company_id
 * @property DocumentKind $kind
 * @property string|null $customer_id
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
 * @property string|null $customer_reference
 * @property string|null $terms_and_conditions
 * @property string|null $notes
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
    'currency_precision', 'document_language', 'customer_reference', 'terms_and_conditions',
    'notes', 'subtotal', 'tax_total', 'total', 'edit_version', 'content_version',
])]
class Document extends TenantOwnedModel
{
    /** @return HasMany<DocumentLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(DocumentLine::class)->orderBy('position');
    }

    /** @return HasOne<DocumentCompanySnapshot, $this> */
    public function companySnapshot(): HasOne
    {
        return $this->hasOne(DocumentCompanySnapshot::class);
    }

    /** @return HasOne<DocumentCustomerSnapshot, $this> */
    public function customerSnapshot(): HasOne
    {
        return $this->hasOne(DocumentCustomerSnapshot::class);
    }

    /** @return HasOne<DocumentBankSnapshot, $this> */
    public function bankSnapshot(): HasOne
    {
        return $this->hasOne(DocumentBankSnapshot::class);
    }

    /** @return HasOne<DocumentTaxDefault, $this> */
    public function taxDefault(): HasOne
    {
        return $this->hasOne(DocumentTaxDefault::class);
    }

    /** @return HasOne<DocumentDeliverySetting, $this> */
    public function deliverySetting(): HasOne
    {
        return $this->hasOne(DocumentDeliverySetting::class);
    }

    /** @return HasMany<DocumentDeliveryRecipient, $this> */
    public function deliveryRecipients(): HasMany
    {
        return $this->hasMany(DocumentDeliveryRecipient::class)->orderBy('display_order');
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
