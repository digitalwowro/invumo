<?php

namespace App\Modules\Documents\Contracts;

use App\Modules\Documents\Data\AllocatedDocumentNumber;
use App\Modules\Documents\Data\DocumentKind;

interface AllocatesDocumentNumbers
{
    /** Caller owns the surrounding transaction and Company lock. */
    public function next(DocumentKind $kind, int $companyLocalYear): AllocatedDocumentNumber;
}
