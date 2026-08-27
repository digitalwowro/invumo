<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Tenancy\TenantContext;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Models\PublicDocumentLink;
use App\Modules\Documents\Models\DocumentDeliverySetting;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PublicDocumentTestCase;

final class CompanyPublicLinkDefaultsHttpTest extends PublicDocumentTestCase
{
    public function test_owner_sees_approved_defaults_and_localized_bounds(): void
    {
        [$owner, $company] = $this->company();

        $this->actingAs($owner)
            ->get(route('company-document-defaults.edit', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('documentDefaults.publicLinksEnabled', true)
                ->where('documentDefaults.publicLinkValidityDays', '30')
                ->where('documentLimits.publicLinkValidityDays.min', 1)
                ->where('documentLimits.publicLinkValidityDays.max', 3650)
                ->where(
                    'translations.settings.documents.fields.public_links_enabled_by_default',
                    'Enable public links for new documents',
                ));

        $owner->update(['language_code' => 'ro']);
        $this->get(route('company-document-defaults.edit', $company))
            ->assertInertia(fn (Assert $page) => $page->where(
                'translations.settings.documents.fields.default_public_link_validity_days',
                'Valabilitatea linkului public în zile',
            ));
    }

    public function test_owner_updates_defaults_and_audit_retains_only_safe_values(): void
    {
        [$owner, $company] = $this->company();

        $this->actingAs($owner)
            ->patch(route('company-document-defaults.update', $company), $this->payload([
                'public_links_enabled_by_default' => false,
                'default_public_link_validity_days' => '3650',
            ]))
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->tenant($company, function (): void {
            $settings = CompanySetting::query()->firstOrFail();
            $audit = AuditEvent::query()
                ->where('action', 'company.document_defaults.updated')
                ->sole();

            $this->assertFalse($settings->public_links_enabled_by_default);
            $this->assertSame(3650, $settings->default_public_link_validity_days);
            $this->assertSame(false, $audit->after['public_links_enabled_by_default']);
            $this->assertSame(3650, $audit->after['default_public_link_validity_days']);
            $this->assertEqualsCanonicalizing([
                'changed_fields',
                'default_payment_term_days',
                'public_links_enabled_by_default',
                'default_public_link_validity_days',
            ], array_keys($audit->after ?? []));
        });
    }

    public function test_validity_bounds_are_enforced_by_http_and_database(): void
    {
        [$owner, $company] = $this->company();

        foreach (['0', '3651'] as $days) {
            $this->actingAs($owner)
                ->patch(route('company-document-defaults.update', $company), $this->payload([
                    'default_public_link_validity_days' => $days,
                ]))
                ->assertSessionHasErrors('default_public_link_validity_days');
        }
        $this->patch(route('company-document-defaults.update', $company), $this->payload([
            'public_links_enabled_by_default' => 'yes',
        ]))->assertSessionHasErrors('public_links_enabled_by_default');
        $missingEnabled = $this->payload();
        unset($missingEnabled['public_links_enabled_by_default']);
        $this->patch(route('company-document-defaults.update', $company), $missingEnabled)
            ->assertSessionHasErrors('public_links_enabled_by_default');

        $this->expectException(QueryException::class);
        $this->tenant($company, fn () => CompanySetting::query()->firstOrFail()->update([
            'default_public_link_validity_days' => 0,
        ]));
    }

    public function test_enabled_default_is_copied_only_to_future_documents_without_creating_tokens(): void
    {
        [$owner, $company] = $this->company();
        $first = $this->quote($company, $owner);

        $this->actingAs($owner)->patch(
            route('company-document-defaults.update', $company),
            $this->payload(['public_links_enabled_by_default' => false]),
        )->assertRedirect();
        $second = $this->quote($company, $owner);

        $this->tenant($company, function () use ($first, $second): void {
            $this->assertTrue(DocumentDeliverySetting::query()
                ->where('document_id', $first->id)->sole()->public_access_enabled);
            $this->assertFalse(DocumentDeliverySetting::query()
                ->where('document_id', $second->id)->sole()->public_access_enabled);
            $this->assertSame(0, PublicDocumentLink::query()->count());
        });
        app(TenantContext::class)->assertClear();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'default_document_language' => 'en',
            'default_payment_term_days' => '0',
            'default_quote_validity_days' => '30',
            'default_terms_and_conditions' => null,
            'default_quote_notes' => null,
            'default_invoice_notes' => null,
            'default_email_attachment_mode' => 'SECURE_LINK_ONLY',
            'public_links_enabled_by_default' => true,
            'default_public_link_validity_days' => '30',
        ], $overrides);
    }
}
