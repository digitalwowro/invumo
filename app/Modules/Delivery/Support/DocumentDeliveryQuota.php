<?php

namespace App\Modules\Delivery\Support;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use LogicException;

final readonly class DocumentDeliveryQuota
{
    public function __construct(private Repository $cache) {}

    public function consume(string $companyId, string $accountId, int $recipientCount): bool
    {
        $windows = [
            ['company', $companyId, 'hour', 3600],
            ['company', $companyId, 'day', 86400],
            ['account', $accountId, 'hour', 3600],
            ['account', $accountId, 'day', 86400],
            ['platform', 'shared-provider', 'hour', 3600],
            ['platform', 'shared-provider', 'day', 86400],
        ];
        $store = $this->cache->getStore();

        if (! $store instanceof LockProvider) {
            throw new LogicException('The document-delivery quota cache must support atomic locks.');
        }

        try {
            return $store->lock('document-delivery:quota-reservation', 10)->block(
                5,
                fn (): bool => $this->reserve($windows, $recipientCount),
            );
        } catch (LockTimeoutException) {
            return false;
        }
    }

    /** @param list<array{string, string, string, int}> $windows */
    private function reserve(array $windows, int $recipientCount): bool
    {
        foreach ($windows as [$scope, $identifier, $period]) {
            $limit = (int) config("invumo.document_delivery.{$scope}_recipients_per_{$period}");

            if ((int) $this->cache->get($this->key($scope, $identifier, $period), 0)
                + $recipientCount > $limit) {
                return false;
            }
        }

        foreach ($windows as [$scope, $identifier, $period, $seconds]) {
            $key = $this->key($scope, $identifier, $period);
            $current = (int) $this->cache->get($key, 0);

            if ($current === 0) {
                $this->cache->put($key, $recipientCount, $seconds);
            } else {
                $this->cache->increment($key, $recipientCount);
            }
        }

        return true;
    }

    private function key(string $scope, string $identifier, string $period): string
    {
        return 'document-delivery:'.$scope.':'.$period.':'.hash('sha256', $identifier);
    }
}
