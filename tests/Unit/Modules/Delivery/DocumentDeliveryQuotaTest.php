<?php

namespace Tests\Unit\Modules\Delivery;

use App\Modules\Delivery\Support\DocumentDeliveryQuota;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Tests\TestCase;

final class DocumentDeliveryQuotaTest extends TestCase
{
    public function test_account_recipient_budget_spans_companies(): void
    {
        config()->set([
            'invumo.document_delivery.company_recipients_per_hour' => 10,
            'invumo.document_delivery.company_recipients_per_day' => 10,
            'invumo.document_delivery.account_recipients_per_hour' => 1,
            'invumo.document_delivery.account_recipients_per_day' => 1,
            'invumo.document_delivery.platform_recipients_per_hour' => 10,
            'invumo.document_delivery.platform_recipients_per_day' => 10,
        ]);
        $quota = new DocumentDeliveryQuota(new Repository(new ArrayStore));

        $this->assertTrue($quota->consume('company-one', 'shared-account', 1));
        $this->assertFalse($quota->consume('company-two', 'shared-account', 1));
    }

    public function test_a_narrow_scope_rejection_does_not_consume_broader_quotas(): void
    {
        config()->set([
            'invumo.document_delivery.company_recipients_per_hour' => 1,
            'invumo.document_delivery.company_recipients_per_day' => 1,
            'invumo.document_delivery.account_recipients_per_hour' => 10,
            'invumo.document_delivery.account_recipients_per_day' => 10,
            'invumo.document_delivery.platform_recipients_per_hour' => 10,
            'invumo.document_delivery.platform_recipients_per_day' => 10,
        ]);
        $quota = new DocumentDeliveryQuota(new Repository(new ArrayStore));

        $this->assertTrue($quota->consume('company-one', 'shared-account', 1));
        $this->assertFalse($quota->consume('company-one', 'shared-account', 1));
        config()->set([
            'invumo.document_delivery.company_recipients_per_hour' => 10,
            'invumo.document_delivery.company_recipients_per_day' => 10,
        ]);
        $this->assertTrue($quota->consume('company-two', 'shared-account', 9));
    }
}
