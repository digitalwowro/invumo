<?php

namespace Tests\Unit\Modules\Delivery;

use App\Modules\Delivery\Data\CompanyEmailTemplateData;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Rules\EmailTemplateDefinition;
use App\Modules\Delivery\Rules\EmailTemplatePlaceholders;
use Tests\TestCase;

final class EmailTemplatePlaceholdersTest extends TestCase
{
    public function test_each_event_accepts_only_its_exact_placeholder_set(): void
    {
        $definition = new EmailTemplateDefinition(new EmailTemplatePlaceholders);

        self::assertSame([], $definition->invalidFields($this->template(
            EmailTemplateEvent::QuoteSent,
            'Quote {{document_number}} valid until {{valid_until}}',
        )));
        self::assertSame(['body'], $definition->invalidFields($this->template(
            EmailTemplateEvent::QuoteSent,
            'Paid {{payment_amount}}',
        )));
        self::assertSame(['body'], $definition->invalidFields($this->template(
            EmailTemplateEvent::InvoiceSent,
            'Unknown {{not_supported}}',
        )));
        self::assertSame(['body'], $definition->invalidFields($this->template(
            EmailTemplateEvent::InvoiceSent,
            'Malformed {due_date}',
        )));
    }

    public function test_rendering_is_single_pass_and_preserves_unavailable_values(): void
    {
        $rendered = (new EmailTemplatePlaceholders)->render(
            $this->template(
                EmailTemplateEvent::PaymentReceived,
                '{{customer_name}} paid {{payment_amount}} on {{payment_date}}',
            ),
            [
                'customer_name' => '{{company_name}}',
                'payment_amount' => '100.00 RON',
            ],
            'Not available',
        );

        self::assertSame(
            '{{company_name}} paid 100.00 RON on Not available',
            $rendered->body,
        );
        self::assertSame('', $rendered->buttonUrl);
    }

    private function template(
        EmailTemplateEvent $event,
        string $body,
    ): CompanyEmailTemplateData {
        return new CompanyEmailTemplateData(
            event: $event,
            languageCode: 'en',
            subject: 'Message from {{company_name}}',
            body: $body,
            buttonLabel: 'View {{document_number}}',
            signature: 'Regards, {{company_name}}',
        );
    }
}
