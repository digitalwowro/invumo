<?php

namespace App\Modules\Delivery\Queries;

use App\Foundation\Tenancy\TenantContext;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Data\PublicDocumentToken;
use App\Modules\Delivery\Data\ResolvedPublicDocument;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

final readonly class ResolvePublicDocument
{
    public function __construct(private TenantContext $tenantContext) {}

    /**
     * @template TResult
     *
     * @param  Closure(ResolvedPublicDocument): TResult  $callback
     * @return TResult|null
     */
    public function run(
        string $token,
        DocumentKind $expectedKind,
        Closure $callback,
    ): mixed {
        $hash = PublicDocumentToken::lookupHash($token);

        if ($hash === null || $this->tenantContext->companyId() !== null) {
            return null;
        }

        $result = $this->connection()->transaction(function () use (
            $hash,
            $expectedKind,
            $callback,
        ): mixed {
            $connection = $this->connection();
            $connection->selectOne(
                "SELECT set_config('app.public_link_hash', ?, true)",
                [$hash],
            );
            $bootstrap = $connection->table('public_document_links')
                ->where('token_hash', $hash)
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->first(['id', 'company_id', 'document_id']);

            if ($bootstrap === null) {
                return null;
            }

            return $this->tenantContext->runAsSystem(
                (string) $bootstrap->company_id,
                fn (): mixed => $this->resolved(
                    (string) $bootstrap->id,
                    $hash,
                    $expectedKind,
                    $callback,
                ),
            );
        });

        $this->tenantContext->assertClear();

        return $result;
    }

    /** @param Closure(ResolvedPublicDocument): mixed $callback */
    private function resolved(
        string $linkId,
        string $hash,
        DocumentKind $expectedKind,
        Closure $callback,
    ): mixed {
        $link = PublicDocumentLink::query()->whereKey($linkId)->first();

        if (! $link instanceof PublicDocumentLink
            || ! hash_equals($link->token_hash, $hash)
            || $link->revoked_at !== null
            || ! $link->expires_at->isFuture()) {
            return null;
        }

        $company = Company::query()
            ->join('accounts', 'accounts.id', '=', 'companies.owning_account_id')
            ->where('companies.id', $link->company_id)
            ->whereNull('companies.archived_at')
            ->whereNull('accounts.suspended_at')
            ->select('companies.*')
            ->first();
        $document = Document::query()
            ->whereKey($link->document_id)
            ->where('kind', $expectedKind)
            ->first();
        $enabled = $document instanceof Document
            && DocumentDeliverySetting::query()
                ->where('document_id', $document->id)
                ->where('public_access_enabled', true)
                ->exists();

        if (! $company instanceof Company || ! $document instanceof Document || ! $enabled) {
            return null;
        }

        return $callback(new ResolvedPublicDocument($company, $document, $link));
    }

    private function connection(): ConnectionInterface
    {
        return DB::connection(config('database.tenant_connection'));
    }
}
