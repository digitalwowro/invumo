<?php

namespace App\Foundation\Database;

use App\Foundation\Database\Concerns\HasDomainIdentifiers;
use App\Foundation\Tenancy\Concerns\BelongsToTenant;

abstract class TenantOwnedModel extends RuntimeModel
{
    use BelongsToTenant, HasDomainIdentifiers;
}
