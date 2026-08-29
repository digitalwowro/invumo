<?php

namespace App\Modules\Companies\Models;

use App\Foundation\Database\Concerns\HasDomainIdentifiers;
use App\Foundation\Database\RuntimeModel;
use App\Models\User;
use App\Modules\Companies\Data\CompanyRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $company_id
 * @property string|null $invited_email
 * @property string|null $invited_email_normalized
 * @property CompanyRole $role
 * @property string $token_hash
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property CarbonImmutable|null $accepted_at
 * @property string|null $accepted_by_user_id
 * @property string|null $invited_by_user_id
 * @property CarbonImmutable|null $identity_erased_at
 */
#[Fillable([
    'company_id',
    'invited_email',
    'invited_email_normalized',
    'role',
    'token_hash',
    'expires_at',
    'revoked_at',
    'accepted_at',
    'accepted_by_user_id',
    'invited_by_user_id',
    'identity_erased_at',
])]
class CompanyInvitation extends RuntimeModel
{
    use HasDomainIdentifiers;

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => CompanyRole::class,
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'identity_erased_at' => 'immutable_datetime',
        ];
    }
}
