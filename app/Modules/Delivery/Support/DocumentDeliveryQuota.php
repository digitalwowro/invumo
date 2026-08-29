<?php

namespace App\Modules\Delivery\Support;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Date;
use LogicException;

final readonly class DocumentDeliveryQuota
{
    private const LOCK_KEY = 'document-delivery:quota-reservation';

    private const LOCK_SECONDS = 70;

    private const STORAGE_SECONDS = 90000;

    public function __construct(
        private Repository $cache,
        private int $lockWaitSeconds = 5,
    ) {}

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

        return $store->lock(self::LOCK_KEY, self::LOCK_SECONDS)->block(
            $this->lockWaitSeconds,
            fn (): bool => $this->reserve(
                $windows,
                $recipientCount,
                Date::now('UTC')->getTimestamp(),
            ),
        );
    }

    /** @param list<array{string, string, string, int}> $windows */
    private function reserve(array $windows, int $recipientCount, int $now): bool
    {
        $definitions = [];

        foreach ($windows as [$scope, $identifier, $period, $seconds]) {
            $key = $this->key($scope, $identifier, $period);
            $definitions[$key] = [$scope, $period, $seconds];
        }

        $stored = $this->cache->many(array_keys($definitions));
        $resolved = [];

        foreach ($definitions as $key => [$scope, $period, $seconds]) {
            $limit = (int) config("invumo.document_delivery.{$scope}_recipients_per_{$period}");
            [$count, $expiresAt] = $this->counter($stored[$key] ?? null, $now, $seconds);

            if ($count + $recipientCount > $limit) {
                return false;
            }

            $resolved[$key] = ['count' => $count, 'expires_at' => $expiresAt];
        }

        $next = array_map(
            fn (array $counter): array => [
                'count' => $counter['count'] + $recipientCount,
                'expires_at' => $counter['expires_at'],
            ],
            $resolved,
        );

        if (! $this->cache->putMany($next, self::STORAGE_SECONDS)) {
            throw new LogicException('The document-delivery quota reservation could not be persisted.');
        }

        return true;
    }

    /** @return array{int, int} */
    private function counter(mixed $stored, int $now, int $seconds): array
    {
        if (is_array($stored)
            && is_int($stored['count'] ?? null)
            && is_int($stored['expires_at'] ?? null)
            && $stored['expires_at'] > $now) {
            return [$stored['count'], $stored['expires_at']];
        }

        if (is_int($stored) && $stored >= 0) {
            return [$stored, $now + $seconds];
        }

        return [0, $now + $seconds];
    }

    private function key(string $scope, string $identifier, string $period): string
    {
        return 'document-delivery:'.$scope.':'.$period.':'.hash('sha256', $identifier);
    }
}
