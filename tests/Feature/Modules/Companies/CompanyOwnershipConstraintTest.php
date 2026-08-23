<?php

namespace Tests\Feature\Modules\Companies;

use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use PDOException;
use Tests\TestCase;

class CompanyOwnershipConstraintTest extends TestCase
{
    use DatabaseMigrations;

    public function test_company_creation_atomically_creates_the_matching_owner(): void
    {
        [$user, $account] = $this->accountOwner();

        $company = app(CreateCompany::class)->handle($account, $user, 'Acme SRL');

        $this->assertTrue(Str::isUuid($user->id, 7));
        $this->assertTrue(Str::isUuid($account->id, 7));
        $this->assertTrue(Str::isUuid($company->id, 7));
        $this->assertDatabaseHas('company_memberships', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => CompanyRole::Owner->value,
        ]);
    }

    public function test_company_cannot_commit_without_an_owner_membership(): void
    {
        [, $account] = $this->accountOwner();

        $this->expectException(PDOException::class);

        DB::connection('pgsql')->transaction(fn () => Company::query()->create([
            'owning_account_id' => $account->id,
            'name' => 'Ownerless SRL',
        ]));
    }

    public function test_owner_membership_must_match_the_owning_account(): void
    {
        [, $account] = $this->accountOwner();
        $otherUser = User::factory()->create();

        $this->expectException(PDOException::class);

        DB::connection('pgsql')->transaction(function () use ($account, $otherUser): void {
            $company = Company::query()->create([
                'owning_account_id' => $account->id,
                'name' => 'Mismatched SRL',
            ]);

            $company->memberships()->create([
                'user_id' => $otherUser->id,
                'role' => CompanyRole::Owner,
            ]);
        });
    }

    public function test_non_owner_cannot_create_a_company_for_an_account(): void
    {
        [, $account] = $this->accountOwner();
        $otherUser = User::factory()->create();

        $this->expectException(LogicException::class);

        app(CreateCompany::class)->handle($account, $otherUser, 'Forbidden SRL');
    }

    public function test_restricted_runtime_role_can_commit_a_valid_company_owner_pair(): void
    {
        [$user, $account] = $this->accountOwner();
        $companyId = (string) Str::uuid7();

        DB::connection('pgsql')->transaction(function () use ($account, $companyId, $user): void {
            DB::connection('pgsql')->table('companies')->insert([
                'id' => $companyId,
                'owning_account_id' => $account->id,
                'name' => 'Runtime SRL',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::connection('pgsql')->table('company_memberships')->insert([
                'id' => (string) Str::uuid7(),
                'company_id' => $companyId,
                'user_id' => $user->id,
                'role' => CompanyRole::Owner->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->assertDatabaseHas('companies', ['id' => $companyId]);
    }

    /**
     * @return array{User, Account}
     */
    private function accountOwner(): array
    {
        $user = User::factory()->create();
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        return [$user, $account];
    }
}
