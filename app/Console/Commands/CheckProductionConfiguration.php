<?php

namespace App\Console\Commands;

use App\Foundation\Configuration\ProductionConfiguration;
use Illuminate\Console\Command;
use RuntimeException;

final class CheckProductionConfiguration extends Command
{
    protected $signature = 'invumo:production-configuration';

    protected $description = 'Verify the production configuration contract';

    public function handle(ProductionConfiguration $configuration): int
    {
        try {
            $configuration->assertSafe();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Invumo production configuration is safe.');

        return self::SUCCESS;
    }
}
