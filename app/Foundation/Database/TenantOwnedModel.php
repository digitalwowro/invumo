<?php

namespace App\Foundation\Database;

use App\Foundation\Database\Concerns\HasDomainIdentifiers;
use App\Foundation\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

abstract class TenantOwnedModel extends Model
{
    use BelongsToTenant, HasDomainIdentifiers;

    public function getConnectionName(): string
    {
        return config('database.tenant_connection');
    }
}
