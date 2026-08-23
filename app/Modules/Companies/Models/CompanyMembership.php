<?php

namespace App\Modules\Companies\Models;

use App\Foundation\Database\Concerns\HasDomainIdentifiers;
use App\Models\User;
use App\Modules\Companies\Data\CompanyRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'user_id', 'role'])]
class CompanyMembership extends Model
{
    use HasDomainIdentifiers;

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['role' => CompanyRole::class];
    }
}
