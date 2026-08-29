<?php

namespace Tests\Unit\Modules\Companies;

use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Policies\CompanyAuthorization;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CompanyAuthorizationTest extends TestCase
{
    #[DataProvider('recurringAbilities')]
    public function test_recurring_abilities_match_the_approved_role_matrix(
        CompanyRole $role,
        CompanyAbility $ability,
        bool $expected,
    ): void {
        $this->assertSame($expected, (new CompanyAuthorization)->allows($role, $ability));
    }

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

    /** @return iterable<string, array{CompanyRole}> */
    public static function roles(): iterable
    {
        foreach (CompanyRole::cases() as $role) {
            yield $role->value => [$role];
        }
    }

    /** @return iterable<string, array{CompanyRole, CompanyAbility, bool}> */
    public static function recurringAbilities(): iterable
    {
        yield 'owner may delete templates' => [
            CompanyRole::Owner, CompanyAbility::DeleteRecurringTemplates, true,
        ];
        yield 'admin may delete templates' => [
            CompanyRole::Admin, CompanyAbility::DeleteRecurringTemplates, true,
        ];
        yield 'member may view templates' => [
            CompanyRole::Member, CompanyAbility::ViewRecurringTemplates, true,
        ];
        yield 'member may edit drafts' => [
            CompanyRole::Member, CompanyAbility::ManageRecurringDrafts, true,
        ];
        yield 'member may not delete templates' => [
            CompanyRole::Member, CompanyAbility::DeleteRecurringTemplates, false,
        ];
        yield 'member may not manage automation' => [
            CompanyRole::Member, CompanyAbility::ManageRecurringAutomation, false,
        ];
    }
}
