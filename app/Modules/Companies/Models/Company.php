<?php

namespace App\Modules\Companies\Models;

use App\Foundation\Database\Concerns\HasDomainIdentifiers;
use App\Modules\Identity\Models\Account;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['owning_account_id', 'name', 'archived_at'])]
class Company extends Model
{
    use HasDomainIdentifiers;

    /**
     * @return BelongsTo<Account, $this>
     */
    public function owningAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'owning_account_id');
    }

    /**
     * @return HasMany<CompanyMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }

    /**
     * @return HasMany<CompanyInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(CompanyInvitation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['archived_at' => 'immutable_datetime'];
    }
}
