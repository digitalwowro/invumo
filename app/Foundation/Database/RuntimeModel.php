<?php

namespace App\Foundation\Database;

use Illuminate\Database\Eloquent\Model;

abstract class RuntimeModel extends Model
{
    public function getConnectionName(): string
    {
        return config('database.tenant_connection');
    }
}
