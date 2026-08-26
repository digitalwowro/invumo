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
use App\Modules\Documents\Models\DocumentCustomerSnapshot;
use App\Modules\Documents\Models\DocumentNumberEvent;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use App\Modules\Quotes\Actions\CreateQuoteDraft;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Models\Quote;
use Closure;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class QuoteOperationalHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_quote_dates_reference_and_database_bounds_are_persisted(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);
        $quote = $this->quote($company, $owner);
        $this->actingAs($owner);

        $this->tenant($company, function (): void {
            $stored = Quote::query()->sole();
            $this->assertSame(30, $stored->validity_days);
            $this->assertSame('2026-09-25', $stored->valid_until?->toDateString());
        });

        $this->patch(route('quotes.update', [$company, $quote]), $this->draft([
            'issue_date' => '2026-09-01',
            'validity_days' => 14,
            'valid_until' => '2026-09-20',
            'customer_reference' => 'PO-2026-42',
        ]))->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->tenant($company, function (): void {
            $document = Document::query()->sole();
            $quote = Quote::query()->sole();
            $this->assertSame('2026-09-01', $document->issue_date?->toDateString());
            $this->assertSame('PO-2026-42', $document->customer_reference);
            $this->assertSame(14, $quote->validity_days);
            $this->assertSame('2026-09-20', $quote->valid_until?->toDateString());
        });

        $this->patch(route('quotes.update', [$company, $quote]), $this->draft([
            'edit_version' => 2,
            'issue_date' => '2026-09-01',
            'valid_until' => '2026-08-31',
        ]))->assertSessionHasErrors('valid_until');
        $this->patch(route('quotes.update', [$company, $quote]), $this->draft([
            'edit_version' => 2,
            'customer_reference' => str_repeat('x', 121),
        ]))->assertSessionHasErrors('customer_reference');
    }

    public function test_list_search_status_dates_and_second_cursor_page_work(): void
    {
        $owner = User::factory()->create();
        $company = $this->company($owner);
        $quotes = [];

        foreach (range(1, 27) as $number) {
            $quotes[] = $this->quote($company, $owner);
        }

        $this->tenant($company, function () use ($quotes): void {
            Document::query()->whereKey($quotes[0]->id)->update(['customer_reference' => 'Discount 50%']);
            Document::query()->whereKey($quotes[1]->id)->update(['customer_reference' => 'Discount 500']);
            DocumentCustomerSnapshot::query()->create([
                'document_id' => $quotes[0]->id,
                'type' => 'COMPANY',
                'legal_name' => 'Needle Customer SRL',
            ]);
            Document::query()->whereKey($quotes[2]->id)->update(['issue_date' => '2026-08-24']);
            Quote::query()->whereKey($quotes[2]->id)->update(['valid_until' => '2026-08-25']);
            foreach (array_slice($quotes, -3) as $quote) {
                Document::query()->whereKey($quote->id)->update(['issue_date' => null]);
            }
        });
        $this->actingAs($owner);

        $first = $this->get(route('quotes.index', $company));
        $first->assertInertia(fn (Assert $page) => $page
            ->component('quotes/index')
            ->has('quotes.items', 25)
            ->where('quotes.nextUrl', fn (mixed $value): bool => is_string($value)));
        $this->get((string) $first->inertiaProps('quotes.nextUrl'))
            ->assertInertia(fn (Assert $page) => $page->has('quotes.items', 2));

        $this->get(route('quotes.index', ['company' => $company, 'q' => '50%']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('quotes.items', 1)
                ->where('quotes.items.0.customerReference', 'Discount 50%'));
        $this->get(route('quotes.index', ['company' => $company, 'q' => 'Needle Customer']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('quotes.items', 1)
                ->where('quotes.items.0.customerName', 'Needle Customer SRL'));
        $this->get(route('quotes.index', ['company' => $company, 'status' => 'EXPIRED']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('quotes.items', 1)
                ->where('quotes.items.0.status', 'EXPIRED'));
    }

    public function test_member_corrects_lifecycle_but_only_owner_admin_delete(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $company = $this->company($owner);
        $company->memberships()->create(['user_id' => $admin->id, 'role' => CompanyRole::Admin]);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $quote = $this->quote($company, $owner);
        $adminQuote = $this->quote($company, $owner);
        $lifecycleUrl = route('quotes.lifecycle.update', [$company, $quote]);
        $deleteUrl = route('quotes.destroy', [$company, $quote]);

        $this->actingAs($member)->patch($lifecycleUrl, [
            'lifecycle' => 'ACCEPTED',
            'reason' => 'Customer confirmed outside Invumo',
            'confirmed' => true,
        ])->assertRedirect()->assertSessionHas('status');

        $this->tenant($company, function () use ($quote): void {
            $this->assertSame(QuoteLifecycle::Accepted, Quote::query()->findOrFail($quote->id)->lifecycle);
            $this->assertSame(2, Document::query()->findOrFail($quote->id)->edit_version);
            $audit = AuditEvent::query()->where('action', 'company.quote.lifecycle_corrected')->sole();
            $this->assertSame(['lifecycle'], array_keys($audit->before));
            $this->assertEqualsCanonicalizing(['lifecycle', 'edit_version'], array_keys($audit->after));
            $this->assertSame('Customer confirmed outside Invumo', $audit->reason);
        });

        $this->delete($deleteUrl, [
            'confirmed' => true, 'confirmed_high_risk' => true,
        ])->assertForbidden();
        $this->actingAs($owner)->delete($deleteUrl, [
            'confirmed' => true, 'confirmed_high_risk' => false,
        ])->assertSessionHasErrors('quote');
        $this->delete($deleteUrl, [
            'confirmed' => true, 'confirmed_high_risk' => true,
        ])->assertRedirect(route('quotes.index', $company));
        $this->actingAs($admin)->delete(route('quotes.destroy', [$company, $adminQuote]), [
            'confirmed' => true, 'confirmed_high_risk' => false,
        ])->assertRedirect(route('quotes.index', $company));

        $this->tenant($company, function () use ($quote): void {
            $this->assertSame(0, Document::query()->count());
            $this->assertSame(2, DocumentNumberEvent::query()->where('event_type', 'DELETED')->count());
            $audit = AuditEvent::query()
                ->where('action', 'company.quote.deleted')
                ->where('target_id', $quote->id)
                ->sole();
            $this->assertEqualsCanonicalizing(['lifecycle', 'had_customer'], array_keys($audit->before));
        });
    }

    public function test_cross_company_quote_routes_are_hidden(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $companyA = $this->company($ownerA);
        $companyB = $this->company($ownerB);
        $foreign = $this->quote($companyB, $ownerB);
        $this->actingAs($ownerA);

        $this->get(route('quotes.edit', [$companyA, $foreign]))->assertNotFound();
        $this->patch(route('quotes.lifecycle.update', [$companyA, $foreign]), [
            'lifecycle' => 'SENT', 'reason' => 'Cross tenant', 'confirmed' => true,
        ])->assertNotFound();
        $this->get(route('quotes.index', $companyB))->assertNotFound();
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function draft(array $overrides = []): array
    {
        return [...[
            'edit_version' => 1, 'customer_id' => null, 'customer_confirmation_token' => null,
            'currency_code' => 'RON', 'document_language' => 'en',
            'issue_date' => '2026-08-26', 'validity_days' => 30,
            'valid_until' => '2026-09-25', 'customer_reference' => null,
            'bank_account_id' => null, 'terms_and_conditions' => null,
            'notes' => null, 'lines' => [],
        ], ...$overrides];
    }

    private function company(User $owner): Company
    {
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Quote Operations SRL');
        $this->tenant($company, function (): void {
            CompanySetting::query()->firstOrFail()->update([
                'timezone' => 'Europe/Bucharest', 'default_document_language' => 'en',
            ]);
            CompanyCurrency::query()->create([
                'currency_code' => 'RON', 'currency_precision' => 2,
                'is_default' => true, 'active' => true,
            ]);
        });

        return $company;
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
