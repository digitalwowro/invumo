<?php

namespace App\Modules\Delivery\Models;

use App\Foundation\Database\TenantOwnedModel;
use App\Modules\Delivery\Data\PublicDocumentLinkRevocationKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property string $id
 * @property string $company_id
 * @property string $document_id
 * @property int $generation
 * @property string $token_hash
 * @property string $token_ciphertext
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revoked_by_user_id
 * @property PublicDocumentLinkRevocationKind|null $revocation_kind
 * @property string|null $created_by_user_id
 */
#[Fillable([
    'document_id', 'generation', 'token_hash', 'token_ciphertext', 'expires_at',
    'revoked_at', 'revoked_by_user_id', 'revocation_kind', 'created_by_user_id',
])]
class PublicDocumentLink extends TenantOwnedModel
{
    protected $hidden = ['token_hash', 'token_ciphertext'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'generation' => 'integer',
            'token_ciphertext' => 'encrypted',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'revocation_kind' => PublicDocumentLinkRevocationKind::class,
        ];
    }
}
