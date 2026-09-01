<?php

namespace Tests\Unit\Modules\Documents;

use App\Modules\Documents\Actions\CreateDocumentDraft;
use App\Modules\Documents\Actions\FinalizeDocumentDeletion;
use App\Modules\Documents\Actions\FinalizeDocumentDraftUpdate;
use App\Modules\Documents\Actions\PersistDocumentDraft;
use App\Modules\Documents\Actions\PrepareDocumentDraftUpdate;
use App\Modules\Documents\Actions\RecordDocumentCreated;
use App\Modules\Documents\Actions\RecordDocumentDraftUpdated;
use App\Modules\Documents\Contracts\DeletesDocumentResources;
use App\Modules\Invoices\Actions\ApplyInvoiceDraftChanges;
use App\Modules\Invoices\Actions\CreateInvoiceDraft;
use App\Modules\Invoices\Actions\DeleteInvoice;
use App\Modules\Invoices\Actions\UpdateInvoiceDraft;
use App\Modules\Quotes\Actions\ApplyQuoteDraftChanges;
use App\Modules\Quotes\Actions\CreateQuoteDraft;
use App\Modules\Quotes\Actions\DeleteQuote;
use App\Modules\Quotes\Actions\UpdateQuoteDraft;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

final class DocumentLifecycleOrchestrationTest extends TestCase
{
    /**
     * @param  class-string  $rootAction
     * @param  list<class-string>  $sharedBoundaries
     */
    #[DataProvider('documentRootActions')]
    public function test_document_roots_compose_the_shared_lifecycle_boundaries(
        string $rootAction,
        array $sharedBoundaries,
    ): void {
        $constructor = new ReflectionClass($rootAction)->getConstructor();
        $this->assertNotNull($constructor);
        $dependencies = array_map(
            static fn ($parameter): ?string => ($parameter->getType() instanceof ReflectionNamedType)
                ? $parameter->getType()->getName()
                : null,
            $constructor->getParameters(),
        );

        foreach ($sharedBoundaries as $boundary) {
            $this->assertContains($boundary, $dependencies, "{$rootAction} bypasses {$boundary}.");
        }
    }

    /** @return iterable<string, array{class-string, list<class-string>}> */
    public static function documentRootActions(): iterable
    {
        $apply = [
            PrepareDocumentDraftUpdate::class,
            PersistDocumentDraft::class,
            FinalizeDocumentDraftUpdate::class,
        ];
        $delete = [DeletesDocumentResources::class, FinalizeDocumentDeletion::class];

        yield 'create Quote' => [CreateQuoteDraft::class, [
            CreateDocumentDraft::class, ApplyQuoteDraftChanges::class, RecordDocumentCreated::class,
        ]];
        yield 'create Invoice' => [CreateInvoiceDraft::class, [
            CreateDocumentDraft::class, ApplyInvoiceDraftChanges::class, RecordDocumentCreated::class,
        ]];
        yield 'update Quote' => [UpdateQuoteDraft::class, [
            ApplyQuoteDraftChanges::class, RecordDocumentDraftUpdated::class,
        ]];
        yield 'update Invoice' => [UpdateInvoiceDraft::class, [
            ApplyInvoiceDraftChanges::class, RecordDocumentDraftUpdated::class,
        ]];
        yield 'apply Quote Draft changes' => [ApplyQuoteDraftChanges::class, $apply];
        yield 'apply Invoice Draft changes' => [ApplyInvoiceDraftChanges::class, $apply];
        yield 'delete Quote' => [DeleteQuote::class, $delete];
        yield 'delete Invoice' => [DeleteInvoice::class, $delete];
    }
}
