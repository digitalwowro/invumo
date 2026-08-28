<?php

namespace App\Modules\Delivery\Actions;

use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Models\CompanySetting;
use App\Modules\Delivery\Data\ProviderWebhookEvent;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryAttempt;
use App\Modules\Delivery\Models\EmailProviderEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class RecordProviderDeliveryEvent
{
    public function __construct(private RecordAuditEvent $audit) {}

    public function handle(string $attemptId, ProviderWebhookEvent $event): bool
    {
        CompanySetting::query()->lockForUpdate()->firstOrFail();
        $attempt = EmailDeliveryAttempt::query()
            ->whereKey($attemptId)
            ->where('client_reference', $event->clientReference)
            ->lockForUpdate()
            ->first();

        if (! $attempt instanceof EmailDeliveryAttempt || $attempt->redacted_at !== null) {
            return false;
        }

        $delivery = EmailDelivery::query()->whereKey($attempt->delivery_id)->lockForUpdate()->first();

        if (! $delivery instanceof EmailDelivery || $delivery->redacted_at !== null) {
            return false;
        }

        $existing = EmailProviderEvent::query()
            ->where('provider_name', 'ZEPTOMAIL')
            ->where('provider_event_identifier', $event->providerEventIdentifier)
            ->lockForUpdate()
            ->first();

        if ($existing instanceof EmailProviderEvent) {
            return true;
        }

        $timestamp = now()->toIso8601String();
        DB::connection(config('database.tenant_connection'))->table('email_provider_events')->insert([
            'id' => (string) Str::uuid7(),
            'company_id' => $delivery->company_id,
            'delivery_id' => $delivery->id,
            'provider_name' => 'ZEPTOMAIL',
            'provider_event_identifier' => $event->providerEventIdentifier,
            'event_type' => $event->type->value,
            'occurred_at' => $event->occurredAt->toIso8601String(),
            'received_at' => $event->receivedAt->toIso8601String(),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $column = $event->type->milestoneColumn();
        $current = $delivery->getAttribute($column);

        if ($current === null || $event->occurredAt->lessThan($current)) {
            DB::connection(config('database.tenant_connection'))->statement(
                "UPDATE email_deliveries SET {$column} = ?::timestamptz, updated_at = statement_timestamp() WHERE company_id = ? AND id = ?",
                [$event->occurredAt->toIso8601String(), $delivery->company_id, $delivery->id],
            );
        }

        $this->audit->handle(new AuditEventData(
            actorType: AuditActorType::ProviderWebhook,
            action: 'company.document.delivery.provider_event_recorded',
            targetType: $delivery->document_kind->value === 'QUOTE' ? 'Quote' : 'Invoice',
            targetId: (string) $delivery->document_id,
            after: AuditPayload::fromAllowedFields([
                'delivery_id' => $delivery->id,
                'event_type' => $event->type->value,
            ], ['delivery_id', 'event_type']),
        ));

        return true;
    }
}
