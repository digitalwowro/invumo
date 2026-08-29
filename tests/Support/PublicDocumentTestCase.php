<?php

namespace Tests\Support;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Models\Document;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Invoices\Actions\CreateInvoiceDraft;
use App\Modules\Quotes\Actions\CreateQuoteDraft;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithDeletionPreviews;
use Tests\TestCase;

abstract class PublicDocumentTestCase extends TestCase
{
    use DatabaseMigrations, InteractsWithDeletionPreviews;

    /** @return array{User, Company} */
    protected function company(string $name = 'Public Documents SRL'): array
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, $name);

        $this->tenant($company, function (): void {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'Europe/Bucharest',
                'default_document_language' => 'en',
                'legal_name' => 'Public Documents Legal SRL',
            ]);
            CompanyCurrency::query()->create([
                'currency_code' => 'RON',
                'currency_precision' => 2,
                'is_default' => true,
                'active' => true,
            ]);
        });

        return [$owner, $company];
    }

    protected function quote(Company $company, User $actor): Document
    {
        return app(CreateQuoteDraft::class)->handle($company, $actor, (string) Str::uuid7());
    }

    protected function invoice(Company $company, User $actor): Document
    {
        return app(CreateInvoiceDraft::class)->handle($company, $actor, (string) Str::uuid7());
    }

    protected function currentToken(Company $company, string $documentId): string
    {
        return $this->tenant(
            $company,
            fn (): string => PublicDocumentLink::query()
                ->where('document_id', $documentId)
                ->whereNull('revoked_at')
                ->sole()
                ->token_ciphertext,
        );
    }

    /** @template T @param Closure(): T $callback @return T */
    protected function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
