<?php

namespace Tests\Unit\Modules\Documents;

use App\Foundation\Money\PeriodUnit;
use App\Modules\Documents\Actions\ResolveDocumentLineCustomization;
use App\Modules\Documents\Data\DocumentLineData;
use App\Modules\Documents\Models\DocumentLine;
use PHPUnit\Framework\TestCase;

final class ResolveDocumentLineCustomizationTest extends TestCase
{
    public function test_equivalent_decimal_formatting_does_not_customize_a_default_line(): void
    {
        $line = $this->persistedLine();

        $this->assertFalse((new ResolveDocumentLineCustomization)->for(
            $line,
            $this->data(itemPrice: '1800.00', quantity: '1'),
        ));
    }

    public function test_manual_edit_customizes_and_source_reapplication_resets_the_line(): void
    {
        $line = $this->persistedLine();

        $this->assertTrue((new ResolveDocumentLineCustomization)->for(
            $line,
            $this->data(description: 'Changed'),
        ));
        $this->assertFalse((new ResolveDocumentLineCustomization)->for(
            $line,
            $this->data(description: 'Changed', sourceApplied: true),
        ));
    }

    public function test_a_new_manual_line_is_customized(): void
    {
        $this->assertTrue((new ResolveDocumentLineCustomization)->for(
            new DocumentLine,
            $this->data(productServiceId: null),
        ));
    }

    private function persistedLine(): DocumentLine
    {
        $line = new DocumentLine([
            'product_service_id' => '0198f941-3e02-7000-8000-000000000001',
            'description' => 'Consulting',
            'item_price' => '1800.00000000',
            'quantity' => '1.000000',
            'unit' => 'hour',
            'period_unit' => PeriodUnit::None,
            'period_quantity' => null,
            'discount_percentage' => '0.000000',
            'tax_preset_id' => null,
            'tax_name' => 'VAT',
            'tax_percentage' => '19.000000',
            'is_customized' => false,
        ]);
        $line->exists = true;

        return $line;
    }

    private function data(
        ?string $productServiceId = '0198f941-3e02-7000-8000-000000000001',
        string $description = 'Consulting',
        string $itemPrice = '1800',
        string $quantity = '1',
        bool $sourceApplied = false,
    ): DocumentLineData {
        return new DocumentLineData(
            id: null,
            productServiceId: $productServiceId,
            description: $description,
            itemPrice: $itemPrice,
            quantity: $quantity,
            unit: 'hour',
            periodUnit: PeriodUnit::None,
            periodQuantity: null,
            discountPercentage: '0',
            taxName: 'VAT',
            taxPercentage: '19',
            taxPresetId: null,
            sourceApplied: $sourceApplied,
        );
    }
}
