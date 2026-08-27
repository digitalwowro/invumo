<?php

namespace Tests\Feature\Modules\Delivery;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Data\CustomerType;
use App\Modules\Customers\Models\Customer;
use App\Modules\Delivery\Actions\CreatePublicDocumentLink;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Data\DocumentKind;
use App\Modules\Documents\Models\Document;
use App\Modules\Quotes\Actions\DecidePublicQuote;
use App\Modules\Quotes\Data\PublicQuoteDecision;
use App\Modules\Quotes\Data\PublicQuoteDecisionData;
use App\Modules\Quotes\Data\QuoteLifecycle;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Models\QuotePublicDecision;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PublicDocumentTestCase;

final class PublicQuoteDecisionIdentityErasureTest extends PublicDocumentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-08-27 12:00:00 Europe/Bucharest');
    }

    protected function tearDown(): void
    {
        Company::query()->pluck('id')->each(fn (string $companyId) => app(TenantContext::class)
            ->runAsSystem($companyId, function (): void {
                Quote::query()->update(['lifecycle' => QuoteLifecycle::Draft]);
                PublicDocumentLink::query()->delete();
                Document::query()->where('kind', DocumentKind::Quote)->delete();
            }));
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_owner_irreversibly_redacts_identity_without_deleting_the_decision_or_quote(): void
    {
        [$owner, $company] = $this->company();
        [$quote, $customer, $token, $key] = $this->decidedQuote($company, $owner);

        $this->actingAs($owner)
            ->get(route('customers.show', [$company, $customer]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('publicDecisionIdentity.count', 1)
                ->where(
                    'publicDecisionIdentity.eraseUrl',
                    route('customer-public-decision-identity.destroy', [$company, $customer], false),
                ));
        $this->delete(
            route('customer-public-decision-identity.destroy', [$company, $customer]),
            ['confirmed' => true],
        )->assertRedirect()->assertSessionHas('status');

        $this->tenant($company, function () use ($quote, $customer): void {
            $decision = QuotePublicDecision::query()->sole();
            $audit = AuditEvent::query()
                ->where('action', 'company.customer.public_decision_identity_redacted')
                ->sole();
            $serialized = json_encode($audit->toArray(), JSON_THROW_ON_ERROR);

            $this->assertNull($decision->customer_name);
            $this->assertNull($decision->customer_email);
            $this->assertNotNull($decision->identity_redacted_at);
            $this->assertSame($customer->id, $decision->customer_id);
            $this->assertTrue(Quote::query()->whereKey($quote->id)->exists());
            $this->assertTrue(Customer::query()->whereKey($customer->id)->exists());
            $this->assertTrue($audit->after['identity_redacted']);
            $this->assertSame(1, $audit->after['decision_count']);
            $this->assertStringNotContainsString('Ana Popescu', $serialized);
            $this->assertStringNotContainsString('ana@example.com', $serialized);
        });

        $result = app(DecidePublicQuote::class)->handle($token, $this->decisionData($key));
        $this->assertSame(PublicQuoteDecision::Accepted, $result?->decision);
        $this->tenant(
            $company,
            fn () => $this->assertSame(1, QuotePublicDecision::query()->count()),
        );
    }

    public function test_confirmation_role_and_company_boundaries_are_enforced(): void
    {
        [$owner, $company] = $this->company();
        [, $customer] = $this->decidedQuote($company, $owner);
        [$outsider, $otherCompany] = $this->company('Other Decisions SRL');
        $member = User::factory()->create();
        $company->memberships()->create([
            'user_id' => $member->id,
            'role' => CompanyRole::Member,
        ]);

        $url = route('customer-public-decision-identity.destroy', [$company, $customer]);
        $this->actingAs($owner)->delete($url)->assertSessionHasErrors('confirmed');
        $this->actingAs($member)->delete($url, ['confirmed' => true])->assertForbidden();
        $this->get(route('customers.show', [$company, $customer]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('publicDecisionIdentity.count', 1)
                ->where('publicDecisionIdentity.eraseUrl', null));
        $this->actingAs($outsider)->delete($url, ['confirmed' => true])->assertNotFound();
        $this->actingAs($owner)->delete(
            route('customer-public-decision-identity.destroy', [$otherCompany, $customer]),
            ['confirmed' => true],
        )->assertNotFound();
    }

    public function test_identity_remains_erasable_after_the_quote_customer_changes(): void
    {
        [$owner, $company] = $this->company();
        [$quote, $sourceCustomer] = $this->decidedQuote($company, $owner);

        $this->tenant($company, function () use ($quote): void {
            $replacement = Customer::query()->create([
                'type' => CustomerType::Company,
                'legal_name' => 'Replacement Customer SRL',
            ]);
            Document::query()->whereKey($quote->id)->update(['customer_id' => $replacement->id]);
        });

        $this->actingAs($owner)->delete(
            route('customer-public-decision-identity.destroy', [$company, $sourceCustomer]),
            ['confirmed' => true],
        )->assertRedirect();

        $this->tenant($company, function () use ($sourceCustomer): void {
            $decision = QuotePublicDecision::query()->sole();

            $this->assertSame($sourceCustomer->id, $decision->customer_id);
            $this->assertNull($decision->customer_name);
            $this->assertNull($decision->customer_email);
            $this->assertNotNull($decision->identity_redacted_at);
        });
    }

    public function test_database_allows_only_one_way_identity_redaction(): void
    {
        [$owner, $company] = $this->company();
        [, $customer] = $this->decidedQuote($company, $owner);

        foreach ([
            ['customer_name' => 'Changed'],
            ['decision' => PublicQuoteDecision::Rejected->value],
        ] as $change) {
            try {
                $this->tenant($company, fn () => QuotePublicDecision::query()->update($change));
                $this->fail('An immutable public decision was changed.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->actingAs($owner)->delete(
            route('customer-public-decision-identity.destroy', [$company, $customer]),
            ['confirmed' => true],
        )->assertRedirect();

        try {
            $this->tenant($company, fn () => QuotePublicDecision::query()->update([
                'customer_name' => 'Restored',
                'customer_email' => 'restored@example.com',
                'identity_redacted_at' => null,
            ]));
            $this->fail('A redacted public identity was restored.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    /** @return array{Document, Customer, string, string} */
    private function decidedQuote(Company $company, User $owner): array
    {
        $quote = $this->quote($company, $owner);
        $customer = $this->tenant($company, function () use ($quote): Customer {
            $customer = Customer::query()->create([
                'type' => CustomerType::Company,
                'legal_name' => 'Decision Customer SRL',
            ]);
            Document::query()->whereKey($quote->id)->update(['customer_id' => $customer->id]);
            Quote::query()->whereKey($quote->id)->update(['lifecycle' => QuoteLifecycle::Sent]);

            return $customer;
        });
        $link = app(CreatePublicDocumentLink::class)->handle(
            $company,
            $owner,
            $quote->id,
            DocumentKind::Quote,
        );
        $key = (string) Str::uuid7();
        app(DecidePublicQuote::class)->handle($link->token_ciphertext, $this->decisionData($key));

        return [$quote, $customer, $link->token_ciphertext, $key];
    }

    private function decisionData(string $key): PublicQuoteDecisionData
    {
        return new PublicQuoteDecisionData(
            PublicQuoteDecision::Accepted,
            'Ana Popescu',
            'ana@example.com',
            $key,
        );
    }
}
