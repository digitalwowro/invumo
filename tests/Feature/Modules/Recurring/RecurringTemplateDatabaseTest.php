<?php

namespace Tests\Feature\Modules\Recurring;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Catalog\Actions\ArchiveProductService;
use App\Modules\Catalog\Actions\DeleteProductService;
use App\Modules\Catalog\Exceptions\ProductServiceException;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Actions\DeleteCustomer;
use App\Modules\Customers\Data\CustomerType;
use App\Modules\Customers\Exceptions\CustomerException;
use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Recurring\Models\RecurringTemplate;
use App\Modules\Recurring\Models\RecurringTemplateCustomerValue;
use App\Modules\Recurring\Models\RecurringTemplateDefault;
use App\Modules\Recurring\Models\RecurringTemplateDeliveryRecipient;
use App\Modules\Recurring\Models\RecurringTemplateLine;
use App\Modules\Recurring\Models\RecurringTemplateReminderRule;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RecurringTemplateDatabaseTest extends TestCase
{
    use DatabaseMigrations;

    public function test_tables_are_forced_rls_and_cross_company_writes_fail_closed(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $companyA = $this->company($ownerA);
        $companyB = $this->company($ownerB);
        [$customerB, $templateB] = $this->records($companyB);

        foreach ([
            'recurring_templates', 'recurring_template_lines',
            'recurring_template_customer_values', 'recurring_template_defaults',
            'recurring_template_delivery_recipients', 'recurring_template_reminder_rules',
        ] as $table) {
            $rls = DB::connection('pgsql_schema')->selectOne(<<<SQL
                SELECT relrowsecurity, relforcerowsecurity
                FROM pg_class WHERE oid = 'public.{$table}'::regclass
                SQL);
            $this->assertTrue($rls->relrowsecurity);
            $this->assertTrue($rls->relforcerowsecurity);
            $this->assertSame(0, DB::connection('pgsql_schema')->table($table)->count());
        }

        $this->tenant($companyA, function () use ($companyB, $customerB, $templateB): void {
            $this->assertNull(RecurringTemplate::query()->find($templateB->id));

            $this->expectException(QueryException::class);
            DB::connection(config('database.tenant_connection'))->table('recurring_templates')->insert([
                'id' => (string) Str::uuid7(),
                'company_id' => $companyB->id,
                'client_creation_key' => (string) Str::uuid7(),
                'internal_name' => 'Foreign',
                'customer_id' => $customerB->id,
                'state' => 'DRAFT',
                'edit_version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function test_customer_and_product_deletion_are_blocked_but_product_archive_is_safe(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);
        [$customer, $template] = $this->records($company);
        $product = $this->tenant($company, fn (): ProductService => ProductService::query()->create([
            'name' => 'Support',
            'period_unit' => 'NONE',
        ]));
        $this->tenant($company, fn (): RecurringTemplateLine => RecurringTemplateLine::query()->create([
            'recurring_template_id' => $template->id,
            'position' => 1,
            'product_service_id' => $product->id,
            'description' => 'Detached support snapshot',
            'period_unit' => 'NONE',
            'discount_percentage' => 0,
            'tax_percentage' => 0,
        ]));

        $archived = app(ArchiveProductService::class)->handle($company, $owner, $product->id);
        $this->assertNotNull($archived->archived_at);

        try {
            app(DeleteProductService::class)->handle($company, $owner, $product->id);
            $this->fail('Referenced Product deletion must be blocked.');
        } catch (ProductServiceException $exception) {
            $this->assertSame('dependencies', $exception->reason());
        }

        try {
            app(DeleteCustomer::class)->handle($company, $owner, $customer->id);
            $this->fail('Referenced Customer deletion must be blocked.');
        } catch (CustomerException $exception) {
            $this->assertSame('dependencies', $exception->reason());
        }

        $this->tenant($company, function () use ($customer, $product): void {
            $this->assertNotNull(Customer::query()->find($customer->id));
            $this->assertNotNull(ProductService::query()->find($product->id));
            $this->assertSame('Detached support snapshot', RecurringTemplateLine::query()->sole()->description);
        });
    }

    public function test_position_constraint_is_deferrable_and_draft_envelopes_are_enforced(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);
        [, $template] = $this->records($company);

        $this->tenant($company, function () use ($template): void {
            $first = RecurringTemplateLine::query()->create($this->line($template, 1, 'A'));
            $second = RecurringTemplateLine::query()->create($this->line($template, 2, 'B'));
            $connection = DB::connection(config('database.tenant_connection'));
            $connection->statement(
                'SET CONSTRAINTS recurring_template_lines_company_template_position_unique DEFERRED',
            );
            $first->update(['position' => 2]);
            $second->update(['position' => 1]);
            $connection->statement(
                'SET CONSTRAINTS recurring_template_lines_company_template_position_unique IMMEDIATE',
            );
            $this->assertSame(
                ['B', 'A'],
                RecurringTemplateLine::query()->orderBy('position')->pluck('description')->all(),
            );

            try {
                $connection->transaction(fn () => $template->update(['state' => 'ACTIVE']));
                $this->fail('An Active template requires complete scheduling runtime state.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->errorInfo[0]);
            }
        });
    }

    public function test_override_child_rows_require_their_explicit_parent_modes(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);
        [, $template] = $this->records($company);

        try {
            $this->tenant($company, function () use ($template): void {
                RecurringTemplateCustomerValue::query()->create([
                    'recurring_template_id' => $template->id,
                    'explicit_fields' => [],
                ]);
                RecurringTemplateDeliveryRecipient::query()->create([
                    'recurring_template_id' => $template->id,
                    'role' => 'TO', 'email' => 'test@example.com', 'display_order' => 1,
                ]);
            });
            $this->fail('Recipient rows require explicit recipient mode.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0]);
        }

        try {
            $this->tenant($company, function () use ($template): void {
                RecurringTemplateDefault::query()->create([
                    'recurring_template_id' => $template->id,
                    'reminder_mode' => 'INHERIT_COMPANY',
                ]);
                RecurringTemplateReminderRule::query()->create([
                    'recurring_template_id' => $template->id,
                    'relation' => 'AFTER_DUE', 'day_offset' => 1,
                    'enabled' => true, 'display_order' => 1,
                ]);
            });
            $this->fail('Reminder rows require override mode.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0]);
        }
    }

    public function test_recurring_templates_must_be_inserted_as_drafts(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);
        $customer = $this->tenant($company, fn (): Customer => Customer::query()->create([
            'type' => CustomerType::Company,
            'legal_name' => 'Lifecycle Customer SRL',
        ]));

        try {
            $this->tenant($company, fn (): RecurringTemplate => RecurringTemplate::query()->create([
                'client_creation_key' => (string) Str::uuid7(),
                'internal_name' => 'Invalid completed insert',
                'customer_id' => $customer->id,
                'state' => 'COMPLETED',
                'completed_at' => now(),
            ]));
            $this->fail('A recurring template must begin as a Draft.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0]);
        }
    }

    /** @return array{Customer, RecurringTemplate} */
    private function records(Company $company): array
    {
        return $this->tenant($company, function (): array {
            $customer = Customer::query()->create([
                'type' => CustomerType::Company,
                'legal_name' => 'Customer SRL',
            ]);
            $template = RecurringTemplate::query()->create([
                'client_creation_key' => (string) Str::uuid7(),
                'internal_name' => 'Monthly customer',
                'customer_id' => $customer->id,
            ]);

            return [$customer, $template];
        });
    }

    /** @return array<string, mixed> */
    private function line(RecurringTemplate $template, int $position, string $description): array
    {
        return [
            'recurring_template_id' => $template->id,
            'position' => $position,
            'description' => $description,
            'period_unit' => 'NONE',
            'discount_percentage' => 0,
            'tax_percentage' => 0,
        ];
    }

    private function company(User $owner): Company
    {
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);

        return app(CreateCompany::class)->handle($account, $owner, 'Recurring Database SRL');
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
