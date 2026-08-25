<?php

namespace Tests\Feature\Modules\Companies;

use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Actions\UpdateNumberSeriesConfiguration;
use App\Modules\Companies\Data\NumberSeriesConfigurationData;
use App\Modules\Companies\Data\NumberSeriesData;
use App\Modules\Companies\Data\NumberSeriesDocumentType;
use App\Modules\Companies\Data\NumberSeriesResetPolicy;
use App\Modules\Companies\Exceptions\NumberSeriesException;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

final class UpdateNumberSeriesConfigurationActionTest extends TestCase
{
    use DatabaseMigrations;

    public function test_action_rejects_annual_reset_without_a_year_token(): void
    {
        $owner = User::factory()->create();
        $plan = Plan::query()->where('code', 'free')->firstOrFail();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => $plan->id,
        ]);
        $company = app(CreateCompany::class)->handle($account, $owner, 'Acme SRL');

        try {
            app(UpdateNumberSeriesConfiguration::class)->handle(
                $company,
                $owner,
                new NumberSeriesConfigurationData(
                    quote: new NumberSeriesData(
                        NumberSeriesDocumentType::Quote,
                        'Q-{NUMBER}',
                        4,
                        NumberSeriesResetPolicy::Annual,
                    ),
                    invoice: new NumberSeriesData(
                        NumberSeriesDocumentType::Invoice,
                        'I-{YEAR}-{NUMBER}',
                        4,
                        NumberSeriesResetPolicy::Never,
                    ),
                ),
            );

            $this->fail('The invalid annual series was accepted.');
        } catch (NumberSeriesException $exception) {
            $this->assertSame('invalid_configuration', $exception->reason());
            $this->assertSame(['quote.pattern'], $exception->fields());
        }
    }
}
