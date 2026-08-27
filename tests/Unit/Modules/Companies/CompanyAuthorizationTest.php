<?php

namespace Tests\Unit\Modules\Companies;

use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Policies\CompanyAuthorization;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CompanyAuthorizationTest extends TestCase
{
    #[DataProvider('roles')]
    public function test_transaction_list_access_never_exceeds_invoice_visibility(CompanyRole $role): void
    {
        $authorization = new CompanyAuthorization;

        if ($authorization->allows($role, CompanyAbility::ViewTransactions)) {
            $this->assertTrue($authorization->allows($role, CompanyAbility::ViewInvoices));
        } else {
            $this->addToAssertionCount(1);
        }
    }

    public static function roles(): iterable
    {
        foreach (CompanyRole::cases() as $role) {
            yield $role->value => [$role];
        }
    }
}
