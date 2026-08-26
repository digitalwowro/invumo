<?php

namespace Tests\Feature\Modules\Invoices;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Invoices\Actions\CreateInvoiceDraft;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InvoiceListHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_issue_sort_cursor_pagination_requests_every_page(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);

        for ($index = 0; $index < 26; $index++) {
            app(CreateInvoiceDraft::class)->handle(
                $company,
                $owner,
                (string) Str::uuid7(),
            );
        }

        $response = $this->actingAs($owner)->get(route('invoices.index', [
            $company,
            'sort' => 'issue_asc',
            'per_page' => 25,
        ]));
        $response->assertInertia(fn (Assert $page) => $page
            ->has('invoices.items', 25)
            ->where('filters.sort', 'issue_asc'));
        $nextUrl = $response->inertiaProps('invoices.nextUrl');
        $this->assertIsString($nextUrl);

        $this->get($nextUrl)->assertInertia(fn (Assert $page) => $page
            ->has('invoices.items', 1)
            ->where('invoices.nextUrl', null));
    }

    private function company(User $owner): Company
    {
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Invoice List SRL');
        $this->tenant($company, fn () => CompanySetting::query()->firstOrFail()->update([
            'timezone' => 'Europe/Bucharest',
            'default_payment_term_days' => 30,
        ]));

        return $company;
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
