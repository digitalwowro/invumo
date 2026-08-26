<?php

namespace Tests\Unit\Modules\Documents;

use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Documents\Data\DocumentKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocumentKindTest extends TestCase
{
    #[DataProvider('abilityMappings')]
    public function test_document_kind_maps_to_its_own_view_and_edit_abilities(
        DocumentKind $kind,
        CompanyAbility $view,
        CompanyAbility $manage,
    ): void {
        $this->assertSame($view, $kind->viewAbility());
        $this->assertSame($manage, $kind->manageAbility());
    }

    /** @return iterable<string, array{DocumentKind, CompanyAbility, CompanyAbility}> */
    public static function abilityMappings(): iterable
    {
        yield 'Quote' => [
            DocumentKind::Quote,
            CompanyAbility::ViewQuotes,
            CompanyAbility::ManageQuotes,
        ];
        yield 'Invoice' => [
            DocumentKind::Invoice,
            CompanyAbility::ViewInvoices,
            CompanyAbility::ManageInvoices,
        ];
    }
}
