<?php

namespace Tests\Feature\Foundation\Diagnostics;

use App\Foundation\Diagnostics\OperationalLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class OperationalLoggerTest extends TestCase
{
    public function test_logger_accepts_only_bounded_operational_metadata(): void
    {
        Log::spy();
        $context = [
            'correlation_id' => (string) Str::uuid7(),
            'component' => 'company.invitation',
            'outcome' => 'succeeded',
            'duration_ms' => 12,
        ];

        app(OperationalLogger::class)->info('company.invitation_sent', $context);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('company.invitation_sent', $context);
    }

    public function test_logger_rejects_payloads_identifiers_and_free_text(): void
    {
        foreach (['company_id', 'email', 'token', 'reason', 'payload'] as $key) {
            try {
                app(OperationalLogger::class)->info('company.operation_failed', [
                    $key => 'private value',
                ]);
                $this->fail("Operational logs must reject [{$key}].");
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(
                    "Unsafe operational log context [{$key}].",
                    $exception->getMessage(),
                );
            }
        }
    }

    public function test_logger_requires_a_namespaced_machine_event(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operational log events require a namespaced machine label.');

        app(OperationalLogger::class)->info('Customer email failed');
    }
}
