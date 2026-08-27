<?php

namespace App\Modules\Delivery\Data;

enum PublicDocumentLinkRevocationKind: string
{
    case Explicit = 'EXPLICIT';
    case Regenerated = 'REGENERATED';
}
