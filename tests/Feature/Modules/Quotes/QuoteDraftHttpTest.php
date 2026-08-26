<?php

namespace Tests\Feature\Modules\Quotes;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyCurrency;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentLine;
use App\Modules\Documents\Models\DocumentNumberEvent;
use App\Modules\Documents\Models\NumberCounter;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Quotes\Actions\CreateQuoteDraft;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class QuoteDraftHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_member_creates_an_idempotent_numbered_quote_and_saves_authoritative_lines(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $company = $this->configuredCompany($owner);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $key = (string) Str::uuid7();
        $this->actingAs($member);

        $this->get(route('quotes.create', $company))->assertInertia(fn (Assert $page) => $page
            ->component('quotes/create')
            ->where('translations.create.title', 'New quote'));
        $first = $this->post(route('quotes.store', $company), ['creation_key' => $key]);
        $first->assertRedirect();
        $this->post(route('quotes.store', $company), ['creation_key' => $key])
            ->assertRedirect($first->headers->get('Location'));

        $quote = $this->tenant($company, fn (): Document => Document::query()->sole());
        $this->assertSame('Q-2026-0001', $quote->rendered_number);
        $this->tenant($company, function (): void {
            $this->assertSame(1, NumberCounter::query()->count());
            $this->assertSame(2, NumberCounter::query()->sole()->next_value);
            $this->assertSame(1, DocumentNumberEvent::query()->count());
        });

        $this->get(route('quotes.edit', [$company, $quote]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('quotes/edit')
                ->where('quote.number', 'Q-2026-0001')
                ->where('quote.currencyCode', 'RON')
                ->where('quote.currencyPrecision', 2)
                ->where('quote.editVersion', 1));

        $this->patch(route('quotes.update', [$company, $quote]), [
            ...$this->draftDefaults(),
            'edit_version' => 1,
            'lines' => [$this->line('Consulting', '100', '2', '10', 'TVA', '19')],
        ])->assertRedirect()->assertSessionHas('status');

        $this->tenant($company, function (): void {
            $document = Document::query()->sole();
            $line = DocumentLine::query()->sole();
            $this->assertSame('180.00000000', $document->subtotal);
            $this->assertSame('34.20000000', $document->tax_total);
            $this->assertSame('214.20000000', $document->total);
            $this->assertSame('214.20000000', $line->final_line_total);
            $this->assertSame(2, $document->edit_version);

            $audit = AuditEvent::query()->where('action', 'company.quote.draft_updated')->sole();
            $encoded = json_encode([$audit->before, $audit->after], JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('Consulting', $encoded);
            $this->assertStringNotContainsString('214.2', $encoded);
            $this->assertSame(1, $audit->after['complete_line_count']);
        });
    }

    public function test_complete_aggregate_save_reorders_adds_deletes_and_rejects_stale_versions(): void
    {
        $owner = User::factory()->create();
        $company = $this->configuredCompany($owner);
        $quote = $this->createQuote($company, $owner);
        $this->actingAs($owner);
        $url = route('quotes.update', [$company, $quote]);

        $this->patch($url, [
            ...$this->draftDefaults(),
            'edit_version' => 1,
            'lines' => [$this->line('First'), $this->line('Second')],
        ])->assertRedirect();
        $lines = $this->tenant($company, fn () => DocumentLine::query()->orderBy('position')->get());

        $this->patch($url, [
            ...$this->draftDefaults(),
            'edit_version' => 2,
            'lines' => [
                [...$this->line('Second moved'), 'id' => $lines[1]->id],
                $this->line('Third'),
            ],
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->tenant($company, function () use ($lines): void {
            $current = DocumentLine::query()->orderBy('position')->get();
            $this->assertCount(2, $current);
            $this->assertSame($lines[1]->id, $current[0]->id);
            $this->assertSame([1, 2], $current->pluck('position')->all());
            $this->assertNull(DocumentLine::query()->find($lines[0]->id));
        });

        $this->patch($url, [
            ...$this->draftDefaults(),
            'edit_version' => 2,
            'lines' => [],
        ])->assertSessionHasErrors('edit_version');
    }

    public function test_validation_localization_and_cross_company_line_ownership_fail_closed(): void
    {
        $owner = User::factory()->create(['language_code' => 'ro']);
        $otherOwner = User::factory()->create();
        $company = $this->configuredCompany($owner);
        $other = $this->configuredCompany($otherOwner);
        $quote = $this->createQuote($company, $owner);
        $otherQuote = $this->createQuote($other, $otherOwner);
        $foreignLine = $this->tenant($other, fn (): DocumentLine => DocumentLine::query()->create([
            'document_id' => $otherQuote->id,
            'position' => 1,
            'description' => 'Foreign',
            'period_unit' => 'NONE',
            'discount_percentage' => 0,
            'tax_percentage' => 0,
        ]));
        $this->actingAs($owner);

        $this->patch(route('quotes.update', [$company, $quote]), [
            ...$this->draftDefaults(),
            'edit_version' => 1,
            'lines' => [[...$this->line(str_repeat('x', 5001)), 'id' => $foreignLine->id]],
        ])->assertSessionHasErrors(['lines.0.description']);

        $response = $this->patch(route('quotes.update', [$company, $quote]), [
            ...$this->draftDefaults(),
            'edit_version' => 1,
            'lines' => [[...$this->line('Invalid', '-1'), 'id' => $foreignLine->id]],
        ])->assertSessionHasErrors(['lines.0.item_price']);
        $this->assertStringContainsString('zecimală nenegativă', $response->getSession()->get('errors')->first('lines.0.item_price'));

        $this->get(route('quotes.edit', [$other, $otherQuote]))->assertNotFound();
        $this->patch(route('quotes.update', [$company, $quote]), [
            ...$this->draftDefaults(),
            'edit_version' => 1,
            'lines' => [[...$this->line('Foreign'), 'id' => $foreignLine->id]],
        ])->assertSessionHasErrors('lines');
    }

    /** @return array<string, mixed> */
    private function draftDefaults(): array
    {
        return [
            'customer_id' => null,
            'customer_confirmation_token' => null,
            'currency_code' => 'RON',
            'document_language' => 'en',
            'issue_date' => '2026-08-26',
            'validity_days' => 30,
            'valid_until' => '2026-09-25',
            'customer_reference' => null,
            'bank_account_id' => null,
            'terms_and_conditions' => null,
            'notes' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function line(
        string $description,
        string $price = '10',
        string $quantity = '1',
        string $discount = '0',
        string $taxName = '',
        string $tax = '0',
    ): array {
        return [
            'description' => $description,
            'item_price' => $price,
            'quantity' => $quantity,
            'unit' => 'hour',
            'period_unit' => 'NONE',
            'period_quantity' => null,
            'discount_percentage' => $discount,
            'tax_name' => $taxName,
            'tax_percentage' => $tax,
        ];
    }

    private function configuredCompany(User $owner): Company
    {
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Quote Test SRL');
        $this->tenant($company, function (): void {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'Europe/Bucharest',
                'default_document_language' => 'en',
            ]);
            CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]);
        });

        return $company;
    }

    private function createQuote(Company $company, User $actor): Document
    {
        return app(CreateQuoteDraft::class)
            ->handle($company, $actor, (string) Str::uuid7());
    }

    /** @template T @param Closure(): T $callback @return T */
    private function tenant(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }
}
