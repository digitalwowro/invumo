<?php

namespace Tests\Unit\Modules\Companies;

use App\Modules\Companies\Data\CountryCode;
use App\Modules\Companies\Data\CurrencyCode;
use PHPUnit\Framework\TestCase;

final class CompanyConfigurationOptionsTest extends TestCase
{
    public function test_country_codes_are_unique_uppercase_iso_identifiers(): void
    {
        $codes = CountryCode::all();

        $this->assertCount(249, $codes);
        $this->assertSame($codes, array_values(array_unique($codes)));
        $this->assertContains('RO', $codes);
        $this->assertNotContains('EU', $codes);

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $code);
        }
    }

    public function test_currency_codes_use_the_current_selectable_iso_list(): void
    {
        $codes = CurrencyCode::all();

        $this->assertSame($codes, array_values(array_unique($codes)));
        $this->assertContains('EUR', $codes);
        $this->assertContains('RON', $codes);
        $this->assertContains('ZWG', $codes);
        $this->assertNotContains('BGN', $codes);
        $this->assertNotContains('XTS', $codes);
        $this->assertNotContains('XXX', $codes);

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[A-Z]{3}$/', $code);
        }
    }
}
