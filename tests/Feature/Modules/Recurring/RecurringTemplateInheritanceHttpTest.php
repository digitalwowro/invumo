<?php

namespace Tests\Feature\Modules\Recurring;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\ArchiveBankAccount;
use App\Modules\Companies\Actions\ArchiveTaxPreset;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\BankAccount;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Customers\Actions\ArchiveCustomerContact;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerContact;
use App\Modules\Customers\Queries\ResolveDocumentCustomer;
use App\Modules\Delivery\Models\CompanyReminderRule;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Recurring\Models\RecurringTemplate;
use App\Modules\Recurring\Models\RecurringTemplateCustomerValue;
use App\Modules\Recurring\Models\RecurringTemplateDefault;
use App\Modules\Recurring\Models\RecurringTemplateDeliveryRecipient;
use App\Modules\Recurring\Models\RecurringTemplateLine;
use App\Modules\Recurring\Models\RecurringTemplateReminderRule;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class RecurringTemplateInheritanceHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_explicit_overrides_are_typed_snapshots_and_source_archives_are_safe(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);
        [$template, $customer, $contact, $currency, $tax, $bank, $token] = $this->sources($company);
        $this->actingAs($owner)->patch(
            route('recurring.update', [$company, $template]),
            $this->payload($customer, $contact, $currency, $tax, $bank, $token),
        )->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->tenant($company, function (): void {
            $values = RecurringTemplateCustomerValue::query()->sole();
            $defaults = RecurringTemplateDefault::query()->sole();
            $this->assertSame([
                'identity', 'recipients', 'currency', 'document_language',
                'payment_term_days', 'tax_default', 'email_attachment_mode',
            ], $values->explicit_fields);
            $this->assertSame(4, $values->currency_precision);
            $this->assertSame('Override Customer SRL', $values->legal_name);
            $this->assertSame('Template bank', $defaults->bank_label);
            $this->assertSame('Template terms', $defaults->terms_and_conditions);
            $this->assertSame(1, RecurringTemplateDeliveryRecipient::query()->count());
            $this->assertSame(1, RecurringTemplateReminderRule::query()->count());
            $this->assertSame('10.12345678', RecurringTemplateLine::query()->sole()->item_price);

            $audit = AuditEvent::query()->where('action', 'company.recurring_template.draft_updated')->sole();
            $encoded = json_encode($audit->after, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('billing@example.com', $encoded);
            $this->assertStringNotContainsString('Override Customer', $encoded);
        });

        app(ArchiveTaxPreset::class)->handle($company, $owner, $tax->id);
        app(ArchiveBankAccount::class)->handle($company, $owner, $bank->id);
        app(ArchiveCustomerContact::class)->handle($company, $owner, $customer->id, $contact->id);

        $this->tenant($company, function () use ($tax, $bank, $contact): void {
            $values = RecurringTemplateCustomerValue::query()->sole();
            $defaults = RecurringTemplateDefault::query()->sole();
            $recipient = RecurringTemplateDeliveryRecipient::query()->sole();
            $this->assertSame($tax->id, $values->tax_preset_id);
            $this->assertSame('TVA', $values->tax_name);
            $this->assertSame($bank->id, $defaults->bank_account_id);
            $this->assertSame('Template bank', $defaults->bank_label);
            $this->assertSame($contact->id, $recipient->contact_id);
            $this->assertSame('billing@example.com', $recipient->email);
        });
    }

    public function test_inherited_values_remain_live_while_source_price_keeps_all_eight_decimals(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);
        [$template, $customer, $contact, $currency, $tax, $bank, $token] = $this->sources($company);
        $payload = $this->payload($customer, $contact, $currency, $tax, $bank, $token);
        foreach (['identity', 'recipients', 'currency', 'language', 'payment_term', 'tax', 'delivery'] as $field) {
            $payload['inheritance']["{$field}_mode"] = 'INHERIT';
        }
        $payload['inheritance']['reminder_mode'] = 'INHERIT_COMPANY';
        $payload['inheritance']['reminder_rules'] = [];
        $payload['lines'][0]['tax_mode'] = 'INHERIT_CUSTOMER';
        $payload['lines'][0]['tax_preset_id'] = null;
        $this->actingAs($owner)->patch(route('recurring.update', [$company, $template]), $payload)
            ->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->tenant($company, function () use ($customer): void {
            $currency = CompanyCurrency::query()->create([
                'currency_code' => 'EUR', 'currency_precision' => 2,
                'is_default' => false, 'active' => true,
            ]);
            $customer->update([
                'payment_term_days' => 75,
                'currency_id' => $currency->id,
            ]);
        });

        $this->get(route('recurring.edit', [$company, $template]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('inheritance.paymentTermMode', 'INHERIT')
                ->where('inheritance.paymentTermDays', 75)
                ->where('inheritance.currencyCode', 'EUR')
                ->where('inheritance.currencyPrecision', 2)
                ->where('template.lines.0.itemPrice', '10.12345678')
                ->where('template.lines.0.taxMode', 'INHERIT_CUSTOMER'));
    }

    /** @return array{RecurringTemplate, Customer, CustomerContact, CompanyCurrency, TaxPreset, BankAccount, string} */
    private function sources(Company $company): array
    {
        return $this->tenant($company, function (): array {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'Europe/Bucharest', 'default_document_language' => 'en',
                'default_payment_term_days' => 30, 'default_terms_and_conditions' => 'Company terms',
                'default_invoice_notes' => 'Company notes',
            ]);
            $currency = CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 4,
                'is_default' => true, 'active' => true,
            ]);
            $tax = TaxPreset::query()->create([
                'name' => 'TVA', 'percentage' => '19', 'is_default' => true,
            ]);
            $bank = BankAccount::query()->create([
                'label' => 'Template bank', 'bank_name' => 'Invumo Bank',
                'account_holder' => 'Invumo SRL', 'account_number' => 'RO49AAAA1B31007593840000',
                'is_default' => true,
            ]);
            CompanyReminderRule::query()->create([
                'relation' => 'BEFORE_DUE', 'day_offset' => 3,
                'enabled' => true, 'display_order' => 1,
            ]);
            $customer = Customer::query()->create([
                'type' => 'COMPANY', 'legal_name' => 'Source Customer SRL',
            ]);
            $contact = CustomerContact::query()->create([
                'customer_id' => $customer->id, 'name' => 'Billing Contact',
                'email' => 'billing@example.com', 'display_order' => 1,
            ]);
            $template = RecurringTemplate::query()->create([
                'client_creation_key' => (string) Str::uuid7(),
                'internal_name' => 'Inheritance Test', 'customer_id' => $customer->id,
            ]);
            $token = app(ResolveDocumentCustomer::class)->for($customer->id)->confirmationToken;

            return [$template, $customer, $contact, $currency, $tax, $bank, $token];
        });
    }

    /** @return array<string, mixed> */
    private function payload(
        Customer $customer,
        CustomerContact $contact,
        CompanyCurrency $currency,
        TaxPreset $tax,
        BankAccount $bank,
        string $token,
    ): array {
        return [
            'edit_version' => 1, 'internal_name' => 'Inheritance Test',
            'customer_id' => $customer->id, 'customer_confirmation_token' => $token,
            'customer_reference' => 'SUB-42',
            'lines' => [[
                'description' => 'Precise service', 'item_price' => '10.12345678',
                'quantity' => '1', 'unit' => 'month', 'period_unit' => 'NONE',
                'period_quantity' => null, 'discount_percentage' => '0',
                'tax_name' => 'TVA', 'tax_percentage' => '19',
                'tax_mode' => 'EXPLICIT', 'tax_preset_id' => $tax->id,
            ]],
            'inheritance' => [
                'identity_mode' => 'EXPLICIT',
                'identity' => ['type' => 'COMPANY', 'legal_name' => 'Override Customer SRL'],
                'recipients_mode' => 'EXPLICIT',
                'recipients' => [['role' => 'TO', 'contact_id' => $contact->id, 'name' => 'Billing', 'email' => 'billing@example.com']],
                'currency_mode' => 'EXPLICIT', 'currency_code' => $currency->currency_code,
                'language_mode' => 'EXPLICIT', 'document_language' => 'ro',
                'payment_term_mode' => 'EXPLICIT', 'payment_term_days' => 45,
                'tax_mode' => 'EXPLICIT', 'tax_preset_id' => $tax->id,
                'delivery_mode' => 'EXPLICIT', 'email_attachment_mode' => 'ATTACH_PDF',
                'terms_mode' => 'EXPLICIT', 'terms_and_conditions' => 'Template terms',
                'notes_mode' => 'EXPLICIT', 'notes' => 'Template notes',
                'bank_mode' => 'EXPLICIT', 'bank_account_id' => $bank->id,
                'reminder_mode' => 'OVERRIDE',
                'reminder_rules' => [[
                    'source_rule_id' => null, 'relation' => 'AFTER_DUE',
                    'day_offset' => 5, 'enabled' => true,
                ]],
            ],
        ];
    }

    private function company(User $owner): Company
    {
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);

        return app(CreateCompany::class)->handle($account, $owner, 'Recurring Sources SRL');
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
