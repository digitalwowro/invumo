<?php

declare(strict_types=1);

namespace Tests\Unit\Foundation\Money;

use App\Foundation\Money\DecimalTransport;
use App\Foundation\Money\DocumentCalculator;
use App\Foundation\Money\LineCalculationInput;
use App\Foundation\Money\LineCalculator;
use App\Foundation\Money\PeriodUnit;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CalculationParityTest extends TestCase
{
    #[DataProvider('lineCases')]
    public function test_line_calculation_matches_golden_vector(array $case): void
    {
        $result = (new LineCalculator)->calculate($this->lineInput($case['input']));

        $this->assertSame($case['expected'], $result->toArray());
    }

    #[DataProvider('documentCases')]
    public function test_document_totals_sum_stored_line_values(array $case): void
    {
        $lineCases = [];

        foreach (self::fixture()['line_cases'] as $lineCase) {
            $lineCases[$lineCase['name']] = $lineCase;
        }

        $calculator = new LineCalculator;
        $lines = [];

        foreach ($case['line_cases'] as $name) {
            $lines[] = $calculator->calculate($this->lineInput($lineCases[$name]['input']));
        }

        $result = (new DocumentCalculator)->calculate($lines, $case['currency_precision']);

        $this->assertSame($case['expected'], $result->toArray());
    }

    #[DataProvider('validTransportCases')]
    public function test_transport_normalizes_valid_decimal_strings(array $case): void
    {
        $this->assertSame(
            $case['expected'],
            DecimalTransport::money($case['value'], $case['currency_precision']),
        );
    }

    #[DataProvider('invalidTransportCases')]
    public function test_transport_rejects_unsafe_decimal_values(array $case): void
    {
        $this->expectException(InvalidArgumentException::class);

        DecimalTransport::money($case['value'], $case['currency_precision']);
    }

    #[DataProvider('invalidLineCases')]
    public function test_line_calculation_rejects_invalid_or_unsafe_inputs(array $case): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new LineCalculator)->calculate($this->lineInput($case['input']));
    }

    public static function lineCases(): iterable
    {
        yield from self::namedCases('line_cases');
    }

    public static function documentCases(): iterable
    {
        yield from self::namedCases('document_cases');
    }

    public static function validTransportCases(): iterable
    {
        yield from self::namedCases('valid_transport_cases');
    }

    public static function invalidTransportCases(): iterable
    {
        yield from self::namedCases('invalid_transport_cases');
    }

    public static function invalidLineCases(): iterable
    {
        yield from self::namedCases('invalid_line_cases');
    }

    private function lineInput(array $input): LineCalculationInput
    {
        return new LineCalculationInput(
            unitPrice: $input['unit_price'],
            quantity: $input['quantity'],
            periodUnit: PeriodUnit::from($input['period_unit']),
            periodQuantity: $input['period_quantity'],
            discountPercentage: $input['discount_percentage'],
            taxPercentage: $input['tax_percentage'],
            currencyPrecision: $input['currency_precision'],
        );
    }

    private static function namedCases(string $key): iterable
    {
        foreach (self::fixture()[$key] as $case) {
            yield $case['name'] => [$case];
        }
    }

    private static function fixture(): array
    {
        $contents = file_get_contents(
            dirname(__DIR__, 3).'/Fixtures/Calculation/calculation-vectors.json',
        );

        self::assertNotFalse($contents);

        return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    }
}
