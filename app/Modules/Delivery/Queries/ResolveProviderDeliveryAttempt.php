<?php

namespace App\Modules\Delivery\Queries;

use App\Foundation\Tenancy\TenantContext;
use App\Modules\Delivery\Actions\RecordProviderDeliveryEvent;
use App\Modules\Delivery\Data\ProviderWebhookEvent;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

final readonly class ResolveProviderDeliveryAttempt
{
    public function __construct(
        private TenantContext $tenantContext,
        private RecordProviderDeliveryEvent $record,
    ) {}

    public function handle(ProviderWebhookEvent $event): bool
    {
        if ($this->tenantContext->companyId() !== null) {
            return false;
        }

        $result = $this->connection()->transaction(function () use ($event): bool {
            $this->connection()->selectOne(
                "SELECT set_config('app.provider_attempt_reference', ?, true)",
                [$event->clientReference],
            );
            $attempt = $this->connection()->table('email_delivery_attempts')
                ->where('client_reference', $event->clientReference)
                ->whereNull('redacted_at')
                ->first(['id', 'company_id']);

            if ($attempt === null) {
                return false;
            }

            return $this->tenantContext->runAsSystem(
                (string) $attempt->company_id,
                fn (): bool => $this->record->handle((string) $attempt->id, $event),
            );
        });

        $this->tenantContext->assertClear();

        return $result;
    }

    private function connection(): ConnectionInterface
    {
        return DB::connection(config('database.tenant_connection'));
    }
}
