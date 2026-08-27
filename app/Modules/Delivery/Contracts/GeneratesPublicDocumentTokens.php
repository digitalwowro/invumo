<?php

namespace App\Modules\Delivery\Contracts;

use App\Modules\Delivery\Data\PublicDocumentToken;

interface GeneratesPublicDocumentTokens
{
    public function generate(): PublicDocumentToken;
}
