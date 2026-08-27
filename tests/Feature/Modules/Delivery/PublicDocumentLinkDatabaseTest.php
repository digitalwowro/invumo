<?php

namespace Tests\Feature\Modules\Delivery;

use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Delivery\Actions\CreatePublicDocumentLink;
use App\Modules\Delivery\Actions\ReencryptPublicDocumentLinkTokens;
use App\Modules\Delivery\Contracts\GeneratesPublicDocumentTokens;
use App\Modules\Delivery\Data\PublicDocumentToken;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Data\DocumentKind;
use Illuminate\Database\QueryException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Support\PublicDocumentTestCase;

final class PublicDocumentLinkDatabaseTest extends PublicDocumentTestCase
{
    public function test_storage_is_forced_rls_encrypted_and_audit_is_credential_free(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->quote($company, $owner);
        $link = app(CreatePublicDocumentLink::class)->handle(
            $company,
            $owner,
            $quote->id,
            DocumentKind::Quote,
        );
        $token = $link->token_ciphertext;

        $this->assertSame(0, DB::connection('pgsql_schema')->table('public_document_links')->count());
        $rls = DB::connection('pgsql_schema')->selectOne(<<<'SQL'
            SELECT relrowsecurity, relforcerowsecurity
            FROM pg_class
            WHERE oid = 'public.public_document_links'::regclass
            SQL);
        $this->assertTrue($rls->relrowsecurity);
        $this->assertTrue($rls->relforcerowsecurity);

        $this->tenant($company, function () use ($link, $token): void {
            $raw = DB::connection('pgsql')->table('public_document_links')
                ->where('id', $link->id)->sole();
            $audit = AuditEvent::query()
                ->where('action', 'company.document.public_link.created')
                ->sole();
            $auditJson = json_encode([$audit->before, $audit->after], JSON_THROW_ON_ERROR);

            $this->assertSame(hash('sha256', $token), $raw->token_hash);
            $this->assertNotSame($token, $raw->token_ciphertext);
            $this->assertStringNotContainsString($token, $raw->token_ciphertext);
            $this->assertStringNotContainsString($token, $auditJson);
            $this->assertStringNotContainsString($raw->token_hash, $auditJson);
            $this->assertStringNotContainsString($raw->token_ciphertext, $auditJson);
            $this->assertSame($token, PublicDocumentLink::query()->findOrFail($link->id)->token_ciphertext);
        });
    }

    public function test_hash_bootstrap_exposes_only_one_link_before_company_context(): void
    {
        [$ownerA, $companyA] = $this->company('Alpha Public SRL');
        [$ownerB, $companyB] = $this->company('Beta Public SRL');
        $quoteA = $this->quote($companyA, $ownerA);
        $quoteB = $this->quote($companyB, $ownerB);
        $linkA = app(CreatePublicDocumentLink::class)->handle($companyA, $ownerA, $quoteA->id, DocumentKind::Quote);
        app(CreatePublicDocumentLink::class)->handle($companyB, $ownerB, $quoteB->id, DocumentKind::Quote);
        $connection = DB::connection('pgsql');

        $connection->transaction(function () use ($connection, $linkA, $quoteA): void {
            $connection->selectOne(
                "SELECT set_config('app.public_link_hash', ?, true)",
                [$linkA->token_hash],
            );

            $this->assertSame(1, $connection->table('public_document_links')->count());
            $this->assertSame($linkA->id, $connection->table('public_document_links')->sole()->id);
            $this->assertSame(0, $connection->table('documents')->count());
            $this->assertSame(0, $connection->table('company_settings')->count());

            $connection->selectOne(
                "SELECT set_config('app.current_company_id', ?, true)",
                [$linkA->company_id],
            );
            $this->assertSame($quoteA->id, $connection->table('documents')->sole()->id);
        });

        $this->assertSame(0, $connection->table('public_document_links')->count());
        $this->assertSame(0, $connection->table('documents')->count());
    }

    public function test_database_enforces_current_generation_hash_and_expiry_constraints(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->quote($company, $owner);
        $link = app(CreatePublicDocumentLink::class)->handle($company, $owner, $quote->id, DocumentKind::Quote);

        foreach ([
            ['token_hash' => 'invalid'],
            ['expires_at' => $link->created_at],
            ['generation' => 0],
        ] as $change) {
            try {
                $this->tenant($company, fn () => PublicDocumentLink::query()->create([
                    'document_id' => $quote->id,
                    'generation' => 2,
                    'token_hash' => hash('sha256', random_bytes(32)),
                    'token_ciphertext' => PublicDocumentToken::fromBytes(random_bytes(32))->plainText,
                    'expires_at' => now()->addDay(),
                    'revoked_at' => now(),
                    'revocation_kind' => 'REGENERATED',
                    ...$change,
                ]));
                $this->fail('An invalid public link row was accepted.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(QueryException::class);
        $this->tenant($company, fn () => PublicDocumentLink::query()->create([
            'document_id' => $quote->id,
            'generation' => 2,
            'token_hash' => hash('sha256', random_bytes(32)),
            'token_ciphertext' => PublicDocumentToken::fromBytes(random_bytes(32))->plainText,
            'expires_at' => now()->addDay(),
        ]));
    }

    public function test_previous_key_recovers_then_reencrypts_retained_tokens(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->quote($company, $owner);
        $original = Crypt::getFacadeRoot();
        $oldKey = random_bytes(32);
        $newKey = random_bytes(32);

        try {
            Crypt::swap(new Encrypter($oldKey, 'AES-256-CBC'));
            $link = app(CreatePublicDocumentLink::class)->handle($company, $owner, $quote->id, DocumentKind::Quote);
            $token = $link->token_ciphertext;
            $before = $this->rawCiphertext($company, $link->id);

            Crypt::swap((new Encrypter($newKey, 'AES-256-CBC'))->previousKeys([$oldKey]));
            $this->assertSame($token, $this->currentToken($company, $quote->id));
            $this->assertSame(1, app(ReencryptPublicDocumentLinkTokens::class)->handle($company->id));
            $after = $this->rawCiphertext($company, $link->id);
            $this->assertNotSame($before, $after);

            Crypt::swap(new Encrypter($newKey, 'AES-256-CBC'));
            $this->assertSame($token, $this->currentToken($company, $quote->id));
        } finally {
            Crypt::swap($original);
        }
    }

    public function test_global_hash_collision_retries_with_a_fresh_token(): void
    {
        [$owner, $company] = $this->company();
        $firstDocument = $this->quote($company, $owner);
        $secondDocument = $this->quote($company, $owner);
        $firstToken = PublicDocumentToken::fromBytes(str_repeat('a', 32));
        $secondToken = PublicDocumentToken::fromBytes(str_repeat('b', 32));
        app()->instance(GeneratesPublicDocumentTokens::class, new class($firstToken) implements GeneratesPublicDocumentTokens
        {
            public function __construct(private readonly PublicDocumentToken $token) {}

            public function generate(): PublicDocumentToken
            {
                return $this->token;
            }
        });
        app(CreatePublicDocumentLink::class)->handle(
            $company,
            $owner,
            $firstDocument->id,
            DocumentKind::Quote,
        );
        app()->instance(GeneratesPublicDocumentTokens::class, new class($firstToken, $secondToken) implements GeneratesPublicDocumentTokens
        {
            private int $attempt = 0;

            public function __construct(
                private readonly PublicDocumentToken $collision,
                private readonly PublicDocumentToken $fresh,
            ) {}

            public function generate(): PublicDocumentToken
            {
                return ++$this->attempt === 1 ? $this->collision : $this->fresh;
            }
        });

        $link = app(CreatePublicDocumentLink::class)->handle(
            $company,
            $owner,
            $secondDocument->id,
            DocumentKind::Quote,
        );

        $this->assertSame($secondToken->plainText, $link->token_ciphertext);
        $this->assertSame(2, $this->tenant(
            $company,
            fn (): int => PublicDocumentLink::query()->count(),
        ));
    }

    private function rawCiphertext(object $company, string $linkId): string
    {
        return $this->tenant(
            $company,
            fn (): string => DB::connection('pgsql')->table('public_document_links')
                ->where('id', $linkId)->value('token_ciphertext'),
        );
    }
}
