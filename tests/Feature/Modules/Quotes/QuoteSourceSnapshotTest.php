<?php

namespace Tests\Feature\Modules\Quotes;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Models\BankAccount;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Companies\Models\TaxPreset;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerDeliveryRecipient;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentBankSnapshot;
use App\Modules\Documents\Models\DocumentCompanySnapshot;
use App\Modules\Documents\Models\DocumentCustomerSnapshot;
use App\Modules\Documents\Models\DocumentDeliveryRecipient;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Documents\Models\DocumentTaxDefault;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Quotes\Actions\CreateQuoteDraft;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class QuoteSourceSnapshotTest extends TestCase
{
    use DatabaseMigrations;

    public function test_quote_creation_captures_company_defaults_without_later_source_rewrites(): void
    {
        [$owner, $company] = $this->company();
        [$currency, $tax, $bank] = $this->tenant($company, function (): array {
            $settings = CompanySetting::query()->firstOrFail();
            $settings->update([
                'legal_name' => 'Snapshot SRL',
                'email' => 'billing@snapshot.example',
                'default_terms_and_conditions' => 'Original terms',
                'default_quote_notes' => 'Original note',
            ]);
            $currency = CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]);
            $tax = TaxPreset::query()->create([
                'name' => 'TVA', 'percentage' => '19', 'is_default' => true,
            ]);
            $bank = BankAccount::query()->create([
                'label' => 'RON primary', 'bank_name' => 'Invumo Bank',
                'account_holder' => 'Snapshot SRL', 'account_number' => 'RO49AAAA1B31007593840000',
                'currency_id' => $currency->id, 'is_default' => true,
            ]);

            return [$currency, $tax, $bank];
        });
        $quote = $this->quote($company, $owner);

        $this->tenant($company, function () use ($quote, $tax, $bank): void {
            $document = Document::query()->findOrFail($quote->id);
            $this->assertSame('Original terms', $document->terms_and_conditions);
            $this->assertSame('Original note', $document->notes);
            $this->assertSame('Snapshot SRL', DocumentCompanySnapshot::query()->sole()->legal_name);
            $this->assertSame('billing@snapshot.example', DocumentCompanySnapshot::query()->sole()->email);
            $this->assertSame($tax->id, DocumentTaxDefault::query()->sole()->tax_preset_id);
            $this->assertSame($bank->id, DocumentBankSnapshot::query()->sole()->bank_account_id);
            $this->assertSame('SECURE_LINK_ONLY', DocumentDeliverySetting::query()->sole()->email_attachment_mode->value);

            CompanySetting::query()->firstOrFail()->update([
                'legal_name' => 'Changed SRL',
                'default_terms_and_conditions' => 'Changed terms',
            ]);
            $tax->update(['name' => 'Changed tax']);
            $bank->update(['label' => 'Changed bank']);

            $this->assertSame('Snapshot SRL', DocumentCompanySnapshot::query()->sole()->legal_name);
            $this->assertSame('TVA', DocumentTaxDefault::query()->sole()->name);
            $this->assertSame('RON primary', DocumentBankSnapshot::query()->sole()->label);
            $this->assertSame('Original terms', $document->refresh()->terms_and_conditions);
        });
    }

    public function test_confirmed_customer_selection_snapshots_resolved_defaults_and_rejects_stale_preview(): void
    {
        [$owner, $company] = $this->company();
        [$customer, $eur, $customerTax] = $this->customerSources($company);
        $quote = $this->quote($company, $owner);
        $this->actingAs($owner);
        $preview = $this->getJson(route('quote-sources.customers.show', [$company, $customer]))
            ->assertOk()
            ->json();

        $this->patch(route('quotes.update', [$company, $quote]), [
            ...$this->draft($preview['customerId'], $preview['confirmationToken'], 'EUR', 'ro'),
            'lines' => [$this->line('Detached manual line')],
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->tenant($company, function () use ($customer, $eur, $customerTax): void {
            $document = Document::query()->sole();
            $snapshot = DocumentCustomerSnapshot::query()->sole();
            $this->assertSame($customer->id, $document->customer_id);
            $this->assertSame($eur->currency_code, $document->currency_code);
            $this->assertSame('Customer Example SRL', $snapshot->legal_name);
            $this->assertSame($customerTax->id, DocumentTaxDefault::query()->sole()->tax_preset_id);
            $this->assertSame('ATTACH_PDF', DocumentDeliverySetting::query()->sole()->email_attachment_mode->value);
            $this->assertSame('delivery@example.com', DocumentDeliveryRecipient::query()->sole()->email);
            $this->assertSame('Detached manual line', DocumentLine::query()->sole()->description);

            $audit = AuditEvent::query()->where('action', 'company.quote.draft_updated')->sole();
            $encoded = json_encode($audit->after, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('Customer Example', $encoded);
            $this->assertStringNotContainsString('delivery@example.com', $encoded);
        });

        $staleQuote = $this->quote($company, $owner);
        $stale = $this->getJson(route('quote-sources.customers.show', [$company, $customer]))->json();
        $this->tenant($company, fn () => Customer::query()->whereKey($customer->id)->update([
            'legal_name' => 'Updated Customer SRL',
        ]));
        $refreshed = $this->getJson(route('quote-sources.customers.show', [$company, $customer]))->json();
        $this->assertNotSame($stale['confirmationToken'], $refreshed['confirmationToken']);
        $this->patch(route('quotes.update', [$company, $staleQuote]), [
            ...$this->draft($customer->id, $stale['confirmationToken'], 'EUR', 'ro'),
            'lines' => [],
        ])->assertSessionHasErrors('customer_id');
    }

    public function test_catalog_sources_are_detached_and_archived_sources_cannot_be_newly_attached(): void
    {
        [$owner, $company] = $this->company();
        [$product, $tax] = $this->catalogSources($company);
        $quote = $this->quote($company, $owner);
        $this->actingAs($owner);

        $defaults = $this->getJson(route('quote-sources.products.show', [
            $company, $product, 'currency_code' => 'RON',
        ]))->assertOk()->json();
        $this->assertSame('COPIED', $defaults['priceStatus']);
        $this->assertSame($product->name, $defaults['name']);
        $this->patch(route('quotes.update', [$company, $quote]), [
            ...$this->draft(),
            'lines' => [$this->line(
                $defaults['description'],
                $defaults['sourceProductServiceId'],
                $defaults['tax']['sourceTaxPresetId'],
            )],
        ])->assertSessionDoesntHaveErrors();

        $line = $this->tenant($company, fn (): DocumentLine => DocumentLine::query()->sole());
        $this->assertSame($product->id, $line->product_service_id);
        $this->assertSame($tax->id, $line->tax_preset_id);
        $this->get(route('quotes.edit', [$company, $quote]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('quote.lines.0.productServiceName', $product->name));
        $this->tenant($company, fn () => $product->update(['archived_at' => now()]));

        $this->patch(route('quotes.update', [$company, $quote]), [
            ...$this->draft(editVersion: 2),
            'lines' => [[...$this->line('Still detached', $product->id, $tax->id), 'id' => $line->id]],
        ])->assertSessionDoesntHaveErrors();
        $this->patch(route('quotes.update', [$company, $quote]), [
            ...$this->draft(editVersion: 3),
            'lines' => [
                [...$this->line('Still detached', $product->id, $tax->id), 'id' => $line->id],
                $this->line('Invalid new source', $product->id, $tax->id),
            ],
        ])->assertSessionHasErrors('lines');
    }

    /** @return array{User, Company} */
    private function company(): array
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Quote Sources SRL');
        $this->tenant($company, fn () => CompanySetting::query()->firstOrFail()->update([
            'timezone' => 'Europe/Bucharest', 'default_document_language' => 'en',
        ]));

        return [$owner, $company];
    }

    /** @return array{Customer, CompanyCurrency, TaxPreset} */
    private function customerSources(Company $company): array
    {
        return $this->tenant($company, function (): array {
            CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2, 'is_default' => true, 'active' => true,
            ]);
            $eur = CompanyCurrency::query()->create([
                'currency_code' => 'EUR', 'currency_precision' => 2, 'is_default' => false, 'active' => true,
            ]);
            TaxPreset::query()->create(['name' => 'TVA', 'percentage' => '19', 'is_default' => true]);
            $tax = TaxPreset::query()->create(['name' => 'Zero', 'percentage' => '0', 'is_default' => false]);
            $customer = Customer::query()->create([
                'type' => 'COMPANY', 'legal_name' => 'Customer Example SRL',
                'currency_id' => $eur->id, 'document_language' => 'ro',
                'tax_preset_id' => $tax->id, 'email_attachment_mode' => 'ATTACH_PDF',
            ]);
            CustomerDeliveryRecipient::query()->create([
                'customer_id' => $customer->id, 'role' => 'TO',
                'explicit_name' => 'Billing', 'explicit_email' => 'delivery@example.com', 'display_order' => 1,
            ]);

            return [$customer, $eur, $tax];
        });
    }

    /** @return array{ProductService, TaxPreset} */
    private function catalogSources(Company $company): array
    {
        return $this->tenant($company, function (): array {
            $currency = CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2, 'is_default' => true, 'active' => true,
            ]);
            $tax = TaxPreset::query()->create(['name' => 'TVA', 'percentage' => '19', 'is_default' => true]);
            $product = ProductService::query()->create([
                'name' => 'Consulting', 'unit_price' => '100', 'currency_id' => $currency->id,
                'period_unit' => 'NONE', 'tax_preset_id' => $tax->id,
            ]);

            return [$product, $tax];
        });
    }

    /** @return array<string, mixed> */
    private function draft(?string $customerId = null, ?string $token = null, string $currency = 'RON', string $language = 'en', int $editVersion = 1): array
    {
        return [
            'edit_version' => $editVersion, 'customer_id' => $customerId,
            'customer_confirmation_token' => $token, 'currency_code' => $currency,
            'document_language' => $language, 'bank_account_id' => null,
            'issue_date' => '2026-08-26', 'validity_days' => 30,
            'valid_until' => '2026-09-25', 'customer_reference' => null,
            'terms_and_conditions' => null, 'notes' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function line(string $description, ?string $product = null, ?string $tax = null): array
    {
        return [
            'product_service_id' => $product, 'description' => $description,
            'item_price' => '100', 'quantity' => '1', 'unit' => 'hour',
            'period_unit' => 'NONE', 'period_quantity' => null,
            'discount_percentage' => '0', 'tax_name' => $tax === null ? '' : 'TVA',
            'tax_percentage' => $tax === null ? '0' : '19', 'tax_preset_id' => $tax,
        ];
    }

    private function quote(Company $company, User $owner): Document
    {
        return app(CreateQuoteDraft::class)->handle($company, $owner, (string) Str::uuid7());
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
