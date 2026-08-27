<?php

namespace App\Console\Commands;

use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Actions\ReencryptPublicDocumentLinkTokens;
use Illuminate\Console\Command;

final class ReencryptPublicDocumentLinkTokensCommand extends Command
{
    protected $signature = 'public-document-links:reencrypt';

    protected $description = 'Re-encrypt retained public document credentials with the current application key';

    public function handle(ReencryptPublicDocumentLinkTokens $reencrypt): int
    {
        $links = 0;
        $companies = 0;

        Company::query()->orderBy('id')->pluck('id')->each(
            function (string $companyId) use ($reencrypt, &$links, &$companies): void {
                $links += $reencrypt->handle($companyId);
                $companies++;
            },
        );

        $this->info("Re-encrypted {$links} public document link(s) across {$companies} Company/Companies.");

        return self::SUCCESS;
    }
}
