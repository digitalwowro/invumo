<?php

namespace Tests\Unit\Modules\Delivery;

use App\Modules\Delivery\Support\DocumentDeliveryQuota;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\LockTimeoutException;
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

    public function test_lock_contention_is_retryable_instead_of_reporting_quota_exhaustion(): void
    {
        $store = new ArrayStore;
        $lock = $store->lock('document-delivery:quota-reservation', 70);
        $this->assertTrue($lock->acquire());

        try {
            $this->expectException(LockTimeoutException::class);
            (new DocumentDeliveryQuota(new Repository($store), 0))
                ->consume('company-one', 'account-one', 1);
        } finally {
            $lock->release();
        }
    }
}
