<?php

namespace App\Modules\Companies\Models;

use App\Foundation\Database\Concerns\HasDomainIdentifiers;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
])]
class CompanyInvitation extends Model
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
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
        ];
    }
}
