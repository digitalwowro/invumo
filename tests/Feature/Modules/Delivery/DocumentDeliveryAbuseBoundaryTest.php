<?php

namespace Tests\Feature\Modules\Delivery;

use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Delivery\Contracts\SendsProviderEmail;
use App\Modules\Delivery\Data\ProviderDelivery;
use App\Modules\Delivery\Data\ProviderDeliveryResult;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Delivery\Models\EmailDeliveryRecipient;
use App\Modules\Delivery\Support\DocumentDeliveryQuota;
use App\Modules\Delivery\Support\DocumentDeliveryRateLimitKey;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\DocumentDeliveryTestCase;
use Throwable;

final class DocumentDeliveryAbuseBoundaryTest extends DocumentDeliveryTestCase
{
    public function test_a_single_message_is_bounded_before_any_delivery_is_created(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        $payload = $this->deliveryPayload($quote);
        $payload['recipients'] = array_map(
            fn (int $index): array => [
                'role' => 'TO',
                'name' => null,
                'email' => "recipient-{$index}@example.com",
            ],
            range(1, 11),
        );

        $this->actingAs($owner)
            ->post(route('quotes.deliveries.store', [$company, $quote]), $payload)
            ->assertSessionHasErrors('recipients');
        $this->tenant($company, fn () => $this->assertSame(0, EmailDelivery::query()->count()));
    }

    public function test_the_database_rejects_an_eleventh_recipient(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        $payload = $this->deliveryPayload($quote);
        $payload['recipients'] = array_map(
            fn (int $index): array => [
                'role' => 'TO', 'name' => null, 'email' => "recipient-{$index}@example.com",
            ],
            range(1, 10),
        );
        Queue::fake();
        $this->actingAs($owner)->post(
            route('quotes.deliveries.store', [$company, $quote]),
            $payload,
        )->assertRedirect();

        try {
            $this->tenant($company, function (): void {
                EmailDeliveryRecipient::query()->create([
                    'delivery_id' => EmailDelivery::query()->sole()->id,
                    'role' => 'CC',
                    'name' => null,
                    'email' => 'eleventh@example.com',
                    'display_order' => 11,
                ]);
            });
            $this->fail('The database accepted an eleventh delivery recipient.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('SQLSTATE[23514]', $exception->getMessage());
        }
    }

    public function test_weighted_company_quota_blocks_the_provider_call(): void
    {
        config()->set([
            'invumo.document_delivery.company_recipients_per_hour' => 1,
            'invumo.document_delivery.company_recipients_per_day' => 1,
        ]);
        [$owner, $company] = $this->company();
        Queue::fake();
        $provider = new AbuseBoundaryProvider;

        foreach (range(1, 2) as $number) {
            $quote = $this->completeQuote($company, $this->quote($company, $owner));
            $this->actingAs($owner)->post(
                route('quotes.deliveries.store', [$company, $quote]),
                $this->deliveryPayload($quote),
            );
            $delivery = $this->tenant(
                $company,
                fn (): EmailDelivery => EmailDelivery::query()
                    ->orderByDesc('created_at')->orderByDesc('id')->firstOrFail(),
            );
            $this->executeDeliveryJob($company->id, $delivery->id, $provider);
        }

        $this->assertSame(1, $provider->calls);
        $this->tenant($company, function (): void {
            $blocked = EmailDelivery::query()
                ->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
            $this->assertSame('sending_quota_exceeded', $blocked->failure_category);
        });
    }

    public function test_named_limiter_is_attached_only_to_delivery_submission_routes(): void
    {
        foreach ([
            'quotes.deliveries.store', 'quotes.deliveries.retry',
            'invoices.deliveries.store', 'invoices.deliveries.retry',
        ] as $name) {
            $this->assertContains(
                'throttle:document-delivery',
                Route::getRoutes()->getByName($name)?->gatherMiddleware() ?? [],
            );
        }

        foreach ([
            'quotes.store', 'quotes.invoices.unlink',
            'invoices.store', 'invoices.destroy',
        ] as $name) {
            $this->assertNotContains(
                'throttle:document-delivery',
                Route::getRoutes()->getByName($name)?->gatherMiddleware() ?? [],
            );
        }
    }

    public function test_pre_binding_account_key_aggregates_companies_under_their_owning_account(): void
    {
        [$owner, $first] = $this->company();
        $second = app(CreateCompany::class)->handle(
            $first->owningAccount()->firstOrFail(),
            $owner,
            'Second Delivery Company SRL',
        );

        $this->assertSame(
            DocumentDeliveryRateLimitKey::account($this->requestForCompany($first->id, $owner)),
            DocumentDeliveryRateLimitKey::account($this->requestForCompany($second->id, $owner)),
        );
    }

    public function test_cross_tenant_requests_cannot_exhaust_the_victim_account_limiter(): void
    {
        [$owner, $company] = $this->company();
        $quote = $this->completeQuote($company, $this->quote($company, $owner));
        Queue::fake();

        foreach (User::factory()->count(5)->create() as $outsider) {
            foreach (range(1, 4) as $_attempt) {
                $this->actingAs($outsider)
                    ->post(
                        route('quotes.deliveries.store', [$company, $quote]),
                        $this->deliveryPayload($quote, key: (string) Str::uuid7()),
                    )
                    ->assertNotFound();
            }
        }

        $this->actingAs($owner)
            ->post(
                route('quotes.deliveries.store', [$company, $quote]),
                $this->deliveryPayload($quote),
            )
            ->assertRedirect();
    }

    public function test_concurrent_quota_reservations_cannot_exceed_shared_capacity(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The delivery-quota concurrency test requires pcntl.');
        }

        config()->set([
            'cache.default' => 'database',
            'invumo.document_delivery.company_recipients_per_hour' => 10,
            'invumo.document_delivery.company_recipients_per_day' => 10,
            'invumo.document_delivery.account_recipients_per_hour' => 10,
            'invumo.document_delivery.account_recipients_per_day' => 10,
            'invumo.document_delivery.platform_recipients_per_hour' => 1,
            'invumo.document_delivery.platform_recipients_per_day' => 1,
        ]);
        Cache::setDefaultDriver('database');
        Cache::forgetDriver('database');
        $directory = sys_get_temp_dir().'/invumo-delivery-quota-'.Str::random(12);
        mkdir($directory, 0700);
        $barrier = "{$directory}/start";
        $children = [];

        foreach ([1, 2] as $slot) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                $this->runConcurrentQuotaReservation($slot, $barrier, "{$directory}/{$slot}");
            }

            $this->assertGreaterThan(0, $pid);
            $children[] = $pid;
        }

        touch($barrier);
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertTrue(pcntl_wifexited($status));
            $this->assertSame(0, pcntl_wexitstatus($status));
        }

        $outcomes = [
            trim((string) file_get_contents("{$directory}/1")),
            trim((string) file_get_contents("{$directory}/2")),
        ];
        sort($outcomes);
        $this->assertSame(['allowed', 'rejected'], $outcomes);

        foreach (["{$directory}/1", "{$directory}/2", $barrier] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }

    private function requestForCompany(string $companyId, User $user): Request
    {
        $request = Request::create('/');
        $route = new RoutingRoute('POST', '/', fn (): null => null);
        $route->bind($request);
        $route->setParameter('company', $companyId);
        $request->setRouteResolver(fn (): RoutingRoute => $route);
        $request->setUserResolver(fn (): User => $user);

        return $request;
    }

    private function runConcurrentQuotaReservation(int $slot, string $barrier, string $result): never
    {
        DB::purge('pgsql');
        DB::purge('pgsql_schema');
        Cache::forgetDriver('database');
        $deadline = microtime(true) + 5;

        while (! is_file($barrier) && microtime(true) < $deadline) {
            usleep(1000);
        }

        try {
            $allowed = app(DocumentDeliveryQuota::class)->consume(
                "company-{$slot}",
                "account-{$slot}",
                1,
            );
            file_put_contents($result, $allowed ? 'allowed' : 'rejected', LOCK_EX);
            exit(0);
        } catch (Throwable $exception) {
            file_put_contents($result, $exception::class.': '.$exception->getMessage(), LOCK_EX);
            exit(1);
        }
    }
}

final class AbuseBoundaryProvider implements SendsProviderEmail
{
    public int $calls = 0;

    public function send(ProviderDelivery $delivery): ProviderDeliveryResult
    {
        $this->calls++;

        return ProviderDeliveryResult::accepted('accepted');
    }
}
