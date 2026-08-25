<?php

namespace App\Modules\Identity\Actions;

use App\Foundation\Localization\SupportedLocales;
use App\Models\User;
use App\Modules\Identity\Data\PlanStatus;
use App\Modules\Identity\Models\Plan;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class RegisterUser
{
    public function handle(
        string $name,
        string $email,
        string $password,
        string $languageCode = 'en',
    ): User {
        if (! SupportedLocales::includes($languageCode)) {
            throw new LogicException('The user language is not supported.');
        }

        return DB::connection(config('database.tenant_connection'))
            ->transaction(function () use ($name, $email, $password, $languageCode): User {
                $plan = Plan::query()
                    ->where('code', config('invumo.default_plan_code'))
                    ->where('active', true)
                    ->first();

                if ($plan === null) {
                    throw new LogicException('The default account plan is unavailable.');
                }

                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'language_code' => $languageCode,
                ]);

                $user->account()->create([
                    'plan_id' => $plan->id,
                    'plan_status' => PlanStatus::Active,
                    'plan_started_at' => now(),
                ]);

                return $user;
            });
    }
}
