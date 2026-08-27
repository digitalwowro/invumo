<?php

namespace App\Modules\Delivery\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Data\PublicDocumentToken;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Models\Document;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class ReencryptPublicDocumentLinkTokens
{
    public function __construct(private TenantContext $tenantContext) {}

    public function handle(string $companyId): int
    {
        return $this->tenantContext->runAsSystem(
            $companyId,
            fn (): int => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): int => $this->reencrypt()),
        );
    }

    private function reencrypt(): int
    {
        CompanySetting::query()->lockForUpdate()->firstOrFail();
        Document::query()->orderBy('id')->lockForUpdate()->get(['id']);
        $links = PublicDocumentLink::query()->orderBy('id')->lockForUpdate()->get();

        foreach ($links as $link) {
            $plainText = $link->token_ciphertext;

            if (! PublicDocumentToken::accepts($plainText)) {
                throw new RuntimeException('Stored public document token plaintext is invalid.');
            }

            $link->token_ciphertext = $plainText;
            $link->save();
        }

        return $links->count();
    }
}
