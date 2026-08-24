<?php

namespace App\Modules\Identity\Models;

use App\Foundation\Database\Concerns\HasDomainIdentifiers;
use App\Foundation\Database\RuntimeModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 */
#[Fillable(['code', 'name', 'entitlements', 'active'])]
class Plan extends RuntimeModel
{
    use HasDomainIdentifiers;

    /**
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entitlements' => 'array',
            'active' => 'boolean',
        ];
    }
}
