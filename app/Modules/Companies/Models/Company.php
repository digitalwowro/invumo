<?php

namespace App\Modules\Companies\Models;

use App\Foundation\Database\Concerns\HasDomainIdentifiers;
use App\Foundation\Database\RuntimeModel;
use App\Modules\Identity\Models\Account;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $memberships_count
 * @property-read Account $owningAccount
 */
#[Fillable(['owning_account_id', 'name', 'archived_at'])]
class Company extends RuntimeModel
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
     * @return HasMany<CompanyAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(CompanyAsset::class);
    }

    /**
     * @return HasOne<CompanySetting, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(CompanySetting::class);
    }

    /**
     * @return HasMany<CompanyCurrency, $this>
     */
    public function currencies(): HasMany
    {
        return $this->hasMany(CompanyCurrency::class);
    }

    /**
     * @return HasMany<TaxPreset, $this>
     */
    public function taxPresets(): HasMany
    {
        return $this->hasMany(TaxPreset::class);
    }

    /**
     * @return HasMany<BankAccount, $this>
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    /**
     * @return HasMany<NumberSeries, $this>
     */
    public function numberSeries(): HasMany
    {
        return $this->hasMany(NumberSeries::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['archived_at' => 'immutable_datetime'];
    }
}
