<?php

namespace Tests\Feature\Modules\Customers;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerContact;
use App\Modules\Customers\Models\CustomerDeliveryRecipient;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CustomerContactDeliveryHttpTest extends TestCase
{
    use DatabaseMigrations;

    public function test_owner_manages_contacts_designations_delivery_and_private_audit(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $owner = User::factory()->create();
        $company = $this->companyFor($owner);
        $customer = $this->customer($company);
        $this->actingAs($owner);

        $this->get(route('customer-contacts.index', [$company, $customer]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('customers/contacts')
                ->where('contacts', [])
                ->where('customer.emailAttachmentMode', null)
                ->where('companyEmailAttachmentMode', 'SECURE_LINK_ONLY')
                ->where('abilities.manage', true)
                ->where('translations.contacts.fields.name', 'Name'));

        $this->post(route('customer-contacts.store', [$company, $customer]), $this->contact(
            name: 'Ada Lovelace',
            email: 'ADA@EXAMPLE.COM',
            primary: true,
            billing: true,
        ))->assertRedirect()->assertSessionHas('status');
        $ada = $this->contactNamed($company, 'Ada Lovelace');

        $this->post(route('customer-contacts.store', [$company, $customer]), $this->contact(
            name: 'Grace Hopper',
            email: 'grace@example.com',
            primary: true,
        ))->assertRedirect();
        $grace = $this->contactNamed($company, 'Grace Hopper');

        $this->tenant($company, function () use ($ada, $grace): void {
            $this->assertFalse(CustomerContact::query()->findOrFail($ada->id)->is_primary);
            $this->assertTrue(CustomerContact::query()->findOrFail($ada->id)->is_billing);
            $this->assertTrue(CustomerContact::query()->findOrFail($grace->id)->is_primary);
        });

        $delivery = [
            'email_attachment_mode' => 'ATTACH_PDF',
            'recipients' => [
                ['role' => 'TO', 'contact_id' => $grace->id],
                ['role' => 'CC', 'contact_id' => $ada->id],
                ['role' => 'BCC', 'explicit_name' => 'Accounts', 'explicit_email' => 'ACCOUNTS@EXAMPLE.COM'],
            ],
        ];
        $this->patch(route('customer-delivery.update', [$company, $customer]), $delivery)
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->patch(route('customer-delivery.update', [$company, $customer]), $delivery)
            ->assertRedirect();

        $this->patch(
            route('customer-contacts.update', [$company, $customer, $ada]),
            $this->contact('Ada Lovelace', 'grace@example.com', billing: true),
        )->assertSessionHasErrors('contact');
        $this->post(route('customer-contacts.archive', [$company, $customer, $ada]))
            ->assertSessionHasErrors('contact');

        $this->patch(route('customer-delivery.update', [$company, $customer]), [
            'email_attachment_mode' => '',
            'recipients' => [['role' => 'TO', 'contact_id' => $grace->id]],
        ])->assertRedirect();
        $this->post(route('customer-contacts.archive', [$company, $customer, $ada]))
            ->assertRedirect();
        $this->post(route('customer-contacts.restore', [$company, $customer, $ada]))
            ->assertRedirect();
        $this->post(route('customer-contacts.archive', [$company, $customer, $ada]))
            ->assertRedirect();
        $this->delete(route('customer-contacts.destroy', [$company, $customer, $ada]))
            ->assertRedirect();

        $this->tenant($company, function () use ($customer, $ada): void {
            $this->assertNull(CustomerContact::query()->find($ada->id));
            $this->assertNull(Customer::query()->findOrFail($customer->id)->email_attachment_mode);
            $recipients = CustomerDeliveryRecipient::query()->get();
            $this->assertCount(1, $recipients);
            $this->assertSame('TO', $recipients->firstOrFail()->role->value);

            $events = AuditEvent::query()
                ->whereIn('action', [
                    'company.customer_contact.created',
                    'company.customer_delivery.updated',
                ])->get();
            $this->assertCount(4, $events);
            $deliveryEvents = $events->where('action', 'company.customer_delivery.updated');
            $this->assertCount(2, $deliveryEvents);
            $created = $events->firstWhere('action', 'company.customer_contact.created');
            $this->assertInstanceOf(AuditEvent::class, $created);
            $this->assertSame(
                ['changed_fields', 'is_billing', 'is_primary'],
                $this->sortedKeys($created->after),
            );
            $deliveryEvents->each(function (AuditEvent $event): void {
                $this->assertSame(
                    ['changed_fields', 'email_attachment_mode'],
                    $this->sortedKeys($event->before),
                );
                $this->assertSame(
                    ['changed_fields', 'email_attachment_mode'],
                    $this->sortedKeys($event->after),
                );
            });
            $encoded = $events->toJson();
            $this->assertStringNotContainsString('Ada Lovelace', $encoded);
            $this->assertStringNotContainsString('ada@example.com', $encoded);
            $this->assertStringNotContainsString('accounts@example.com', $encoded);
        });
    }

    public function test_member_manages_contacts_and_delivery_but_cannot_delete_contacts(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $company = $this->companyFor($owner);
        $company->memberships()->create(['user_id' => $member->id, 'role' => CompanyRole::Member]);
        $customer = $this->customer($company);
        $this->actingAs($member);

        $this->post(
            route('customer-contacts.store', [$company, $customer]),
            $this->contact('Member Contact', 'member-contact@example.com'),
        )->assertRedirect();
        $contact = $this->contactNamed($company, 'Member Contact');
        $this->patch(route('customer-delivery.update', [$company, $customer]), [
            'email_attachment_mode' => 'SECURE_LINK_ONLY',
            'recipients' => [['role' => 'TO', 'contact_id' => $contact->id]],
        ])->assertRedirect();
        $this->patch(route('customer-delivery.update', [$company, $customer]), [
            'email_attachment_mode' => null,
            'recipients' => [],
        ])->assertRedirect();
        $this->post(route('customer-contacts.archive', [$company, $customer, $contact]))
            ->assertRedirect();
        $this->delete(route('customer-contacts.destroy', [$company, $customer, $contact]))
            ->assertForbidden();
    }

    public function test_admin_can_manage_and_permanently_delete_an_archived_contact(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $company = $this->companyFor($owner);
        $company->memberships()->create(['user_id' => $admin->id, 'role' => CompanyRole::Admin]);
        $customer = $this->customer($company);
        $this->actingAs($admin);

        $this->post(
            route('customer-contacts.store', [$company, $customer]),
            $this->contact('Admin Contact', 'admin-contact@example.com'),
        )->assertRedirect();
        $contact = $this->contactNamed($company, 'Admin Contact');
        $this->post(route('customer-contacts.archive', [$company, $customer, $contact]))
            ->assertRedirect();
        $this->delete(route('customer-contacts.destroy', [$company, $customer, $contact]))
            ->assertRedirect();
    }

    public function test_delivery_validation_is_localized_and_rejects_invalid_sources_and_duplicates(): void
    {
        $owner = User::factory()->create(['language_code' => 'ro']);
        $company = $this->companyFor($owner);
        $customer = $this->customer($company);
        $this->actingAs($owner);

        $this->post(route('customer-contacts.store', [$company, $customer]), [
            'name' => str_repeat('x', 161),
            'email' => 'invalid',
            'position_title' => str_repeat('x', 161),
            'is_primary' => false,
            'is_billing' => false,
        ])->assertSessionHasErrors(['name', 'email', 'position_title']);

        $this->patch(route('customer-delivery.update', [$company, $customer]), [
            'email_attachment_mode' => 'INLINE',
            'recipients' => [[
                'role' => 'TO',
                'contact_id' => 'not-a-uuid',
                'explicit_email' => 'invalid',
            ]],
        ])->assertSessionHasErrors([
            'email_attachment_mode', 'recipients.0.contact_id', 'recipients.0.explicit_email',
        ]);

        $this->patch(route('customer-delivery.update', [$company, $customer]), [
            'email_attachment_mode' => null,
            'recipients' => [
                ['role' => 'TO', 'explicit_email' => 'same@example.com'],
                ['role' => 'CC', 'explicit_email' => 'SAME@example.com'],
            ],
        ])->assertSessionHasErrors('delivery');

        $this->get(route('customer-contacts.index', [$company, $customer]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('translations.contacts.title', 'Contacte')
                ->where('translations.delivery.roles.TO', 'Către'));
    }

    /** @return array<string, mixed> */
    private function contact(
        string $name,
        ?string $email,
        bool $primary = false,
        bool $billing = false,
    ): array {
        return [
            'name' => $name,
            'email' => $email,
            'phone' => '+40 700 000 000',
            'position_title' => 'Director',
            'is_primary' => $primary,
            'is_billing' => $billing,
        ];
    }

    private function companyFor(User $owner): Company
    {
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $account = Account::query()->create(['owner_user_id' => $owner->id, 'plan_id' => $plan->id]);

        return app(CreateCompany::class)->handle($account, $owner, 'Acme SRL');
    }

    private function customer(Company $company): Customer
    {
        return $this->tenant($company, fn (): Customer => Customer::query()->create([
            'type' => 'COMPANY', 'legal_name' => 'Client SRL',
        ]));
    }

    private function contactNamed(Company $company, string $name): CustomerContact
    {
        return $this->tenant(
            $company,
            fn (): CustomerContact => CustomerContact::query()->where('name', $name)->firstOrFail(),
        );
    }

    private function tenant(Company $company, callable $callback): mixed
    {
        return app(TenantContext::class)->runAsSystem($company->id, $callback);
    }

    /** @param array<string, mixed> $values */
    private function sortedKeys(array $values): array
    {
        $keys = array_keys($values);
        sort($keys);

        return $keys;
    }
}
