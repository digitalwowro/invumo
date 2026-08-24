<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Platform\Actions\GrantPlatformOwner;
use App\Modules\Platform\Actions\RevokePlatformOwner;
use App\Modules\Platform\Exceptions\PlatformOperatorException;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

final class ManagePlatformOwner extends Command
{
    protected $signature = 'invumo:platform-owner
        {operation : grant or revoke}
        {email : Existing Invumo User email}
        {--reason= : Required audit reason}';

    protected $description = 'Grant or revoke the protected Platform Owner role';

    public function handle(
        GrantPlatformOwner $grant,
        RevokePlatformOwner $revoke,
    ): int {
        $operation = Str::lower((string) $this->argument('operation'));
        $email = Str::lower(trim((string) $this->argument('email')));
        $reason = trim((string) ($this->option('reason') ?? ''));

        if (! in_array($operation, ['grant', 'revoke'], true)) {
            $this->error('Operation must be grant or revoke.');

            return self::INVALID;
        }

        if ($reason === '') {
            $this->error('A non-empty --reason is required for platform audit.');

            return self::INVALID;
        }

        $user = User::query()->where('email_normalized', $email)->first();

        if ($user === null) {
            $this->error('No Invumo User has that email address.');

            return self::FAILURE;
        }

        if (! $this->confirm("Confirm {$operation} Platform Owner for {$user->email}?")) {
            $this->warn('No change was made.');

            return self::FAILURE;
        }

        try {
            if ($operation === 'grant') {
                $grant->handle($user->id, $reason);
            } else {
                $revoke->handle($user->id, $reason);
            }
        } catch (PlatformOperatorException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('The Platform Owner change failed. No partial change was retained.');

            return self::FAILURE;
        }

        $this->info("Platform Owner {$operation} completed for {$user->email}.");

        return self::SUCCESS;
    }
}
