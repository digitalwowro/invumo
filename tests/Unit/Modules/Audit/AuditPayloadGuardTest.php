<?php

namespace Tests\Unit\Modules\Audit;

use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Audit\Rules\AuditPayloadGuard;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AuditPayloadGuardTest extends TestCase
{
    public function test_payload_requires_an_explicit_action_field_allowlist(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AuditPayload::fromAllowedFields(
            ['status' => 'ISSUED', 'unreviewed' => 'value'],
            ['status'],
        );
    }

    public function test_nested_secret_shaped_keys_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new AuditPayloadGuard)->ensureSafe([
            'customer' => ['api_token' => 'fake-value'],
        ]);
    }

    #[DataProvider('credentialValues')]
    public function test_unmistakable_credential_values_are_rejected(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new AuditPayloadGuard)->ensureSafe(['summary' => $value]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function credentialValues(): iterable
    {
        yield 'private key' => ['-----BEGIN PRIVATE KEY----- fake'];
        yield 'RSA private key' => ['-----BEGIN RSA PRIVATE KEY----- fake'];
        yield 'authorization header' => ['Authorization: Bearer fake-credential-123456'];
        yield 'standalone bearer value' => ['Bearer fake-credential-123456'];
        yield 'standalone basic value' => ['Basic ZmFrZS11c2VyOmZha2UtcGFzcw=='];
    }

    public function test_legitimate_financial_and_reference_values_remain_valid(): void
    {
        $payload = AuditPayload::fromAllowedFields([
            'status' => 'ISSUED',
            'customer_reference' => 'PO-BEARER-2026-001',
            'total' => '1250.00',
            'reason' => 'Corrected after customer review',
        ], ['status', 'customer_reference', 'total', 'reason']);

        (new AuditPayloadGuard)->ensureSafe($payload->values());

        $this->assertSame('ISSUED', $payload->values()['status']);
    }
}
