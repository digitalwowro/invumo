<?php

namespace App\Modules\Quotes\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Quotes\Data\QuoteLifecycle;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['document_id', 'document_kind', 'lifecycle'])]
class Quote extends TenantOwnedModel
{
    protected $primaryKey = 'document_id';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['lifecycle' => QuoteLifecycle::class];
    }
}
