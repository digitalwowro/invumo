<?php

namespace App\Modules\Delivery\Actions;

use App\Models\User;
use App\Modules\Delivery\Contracts\GeneratesPublicDocumentTokens;
use App\Modules\Delivery\Data\LockedPublicDocumentAccess;
use App\Modules\Delivery\Models\PublicDocumentLink;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class CreatePublicDocumentLinkGeneration
{
    private const MAX_COLLISION_ATTEMPTS = 3;

    public function __construct(private GeneratesPublicDocumentTokens $tokens) {}

    public function handle(
        LockedPublicDocumentAccess $access,
        ?User $actor,
    ): PublicDocumentLink {
        for ($attempt = 1; $attempt <= self::MAX_COLLISION_ATTEMPTS; $attempt++) {
            try {
                return DB::connection(config('database.tenant_connection'))->transaction(
                    function () use ($access, $actor): PublicDocumentLink {
                        $token = $this->tokens->generate();

                        return PublicDocumentLink::query()->create([
                            'document_id' => $access->document->id,
                            'generation' => $access->nextGeneration(),
                            'token_hash' => $token->hash,
                            'token_ciphertext' => $token->plainText,
                            'expires_at' => now()->addDays(
                                $access->settings->default_public_link_validity_days,
                            ),
                            'created_by_user_id' => $actor?->id,
                        ]);
                    },
                );
            } catch (QueryException $exception) {
                if (! $this->isTokenHashCollision($exception)) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('A unique public document token could not be generated.');
    }

    private function isTokenHashCollision(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === '23505'
            && str_contains(
                (string) ($exception->errorInfo[2] ?? ''),
                'public_document_links_token_hash_unique',
            );
    }
}
