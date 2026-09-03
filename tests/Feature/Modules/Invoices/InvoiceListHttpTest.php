<?php

namespace Tests\Feature\Modules\Invoices;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Customers\Data\CustomerType;
use App\Modules\Documents\Models\DocumentCustomerSnapshot;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Invoices\Actions\CreateInvoiceDraft;
use App\Modules\Invoices\Models\Invoice;
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

    public function test_secondary_sorts_request_every_cursor_page(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);
        $documents = [];

        for ($index = 0; $index < 26; $index++) {
            $documents[] = app(CreateInvoiceDraft::class)->handle(
                $company,
                $owner,
                (string) Str::uuid7(),
            );
        }

        $this->tenant($company, function () use ($documents): void {
            foreach ($documents as $index => $document) {
                DocumentCustomerSnapshot::query()->create([
                    'document_id' => $document->id,
                    'type' => CustomerType::Company,
                    'legal_name' => sprintf('Customer %02d', 25 - $index),
                    'email' => sprintf('billing%02d@example.com', $index),
                ]);
                Invoice::query()->whereKey($document->id)->update([
                    'due_date' => $document->issue_date
                        ->addDays($index + 1)
                        ->toDateString(),
                ]);
            }
        });

        foreach (['customer_asc', 'due_asc', 'total_desc', 'total_asc'] as $sort) {
            $response = $this->actingAs($owner)->get(route('invoices.index', [
                $company,
                'sort' => $sort,
                'per_page' => 25,
            ]));
            $response->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('invoices.items', 25)
                ->where('filters.sort', $sort));

            $nextUrl = $response->inertiaProps('invoices.nextUrl');
            $this->assertIsString($nextUrl);
            $this->get($nextUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('invoices.items', 1)
                ->where('invoices.nextUrl', null));
        }
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
