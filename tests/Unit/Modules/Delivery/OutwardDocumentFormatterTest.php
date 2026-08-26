<?php

use App\Foundation\Money\PeriodUnit;
use App\Modules\Companies\Data\CurrencyDisplayStyle;
use App\Modules\Delivery\Support\OutwardDocumentFormatter;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

it('formats exact monetary strings without binary floating point conversion', function (): void {
    $formatter = app(OutwardDocumentFormatter::class);

    expect($formatter->money(
        '1234567890123456789012.12345678',
        8,
        'EUR',
        CurrencyDisplayStyle::Code,
        'en',
    ))->toBe("1,234,567,890,123,456,789,012.12345678\u{00A0}EUR")
        ->and($formatter->money(
            '1234.50',
            2,
            'EUR',
            CurrencyDisplayStyle::Symbol,
            'ro',
        ))->toBe("1.234,50\u{00A0}€")
        ->and($formatter->money(
            '1234.50',
            2,
            'USD',
            CurrencyDisplayStyle::Symbol,
            'en',
        ))->toBe('$1,234.50');
});

it('localizes dates, decimals, and period quantities from Laravel-owned strings', function (): void {
    $formatter = app(OutwardDocumentFormatter::class);

    expect($formatter->date(CarbonImmutable::parse('2026-08-26'), 'ro'))
        ->toContain('2026')
        ->and($formatter->decimal('19.50000000', 'ro'))->toBe('19,5')
        ->and($formatter->quantity('2.000000', 'ore', PeriodUnit::Month, '3.000000', 'ro'))
        ->toBe('2 ore · 3 lună');
});
