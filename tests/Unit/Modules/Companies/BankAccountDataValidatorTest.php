<?php

namespace Tests\Unit\Modules\Companies;

use App\Modules\Companies\Data\BankAccountData;
use App\Modules\Companies\Exceptions\BankAccountException;
use App\Modules\Companies\Rules\BankAccountDataValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BankAccountDataValidatorTest extends TestCase
{
    public function test_approved_flat_routing_fields_are_accepted(): void
    {
        (new BankAccountDataValidator)->validate($this->data([
            'routing_number' => '123456789',
            'sort_code' => '12-34-56',
            'bank_code' => 'BANK',
            'branch_code' => 'BRANCH',
            'transit_number' => '12345',
            'institution_number' => '001',
            'bsb' => '123-456',
            'ifsc' => 'ABCD0123456',
        ]));

        $this->addToAssertionCount(1);
    }

    /** @param array<string, mixed> $routing */
    #[DataProvider('invalidRoutingProvider')]
    public function test_unknown_nested_or_unbounded_routing_values_are_rejected(
        array $routing,
    ): void {
        $this->expectException(BankAccountException::class);
        (new BankAccountDataValidator)->validate($this->data($routing));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidRoutingProvider(): iterable
    {
        yield 'unknown key' => [['unknown' => 'value']];
        yield 'nested value' => [['routing_number' => ['nested']]];
        yield 'overlong value' => [['routing_number' => str_repeat('1', 65)]];
        yield 'blank value' => [['routing_number' => '   ']];
    }

    /** @param array<string, mixed> $routing */
    private function data(array $routing): BankAccountData
    {
        return new BankAccountData(
            label: 'Main',
            bankName: 'Bank',
            accountHolder: 'Holder',
            accountNumber: 'ACCOUNT',
            swiftBic: 'AAAAROBUXXX',
            currencyId: null,
            localRoutingDetails: $routing,
            isDefault: false,
        );
    }
}
