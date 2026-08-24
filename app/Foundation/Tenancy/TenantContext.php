<?php

namespace App\Foundation\Tenancy;

use App\Foundation\Jobs\TenantJobExecution;
use App\Foundation\Tenancy\Contracts\VerifiesTenantMembership;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

final class TenantContext
{
    private ?string $companyId = null;

    public function __construct(
        private VerifiesTenantMembership $memberships,
        private TenantJobExecution $jobExecution,
    ) {}

    public function companyId(): ?string
    {
        return $this->companyId;
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runForMember(User $user, string $companyId, Closure $callback): mixed
    {
        if (! $this->memberships->allows($user, $companyId)) {
            throw new AuthorizationException;
        }

        return $this->run($companyId, $callback);
    }

    /**
     * This entry point accepts only a server-generated Company identifier from
     * a trusted job, scheduler, or already-authorized public bootstrap.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runAsSystem(string $companyId, Closure $callback): mixed
    {
        return $this->run($companyId, $callback);
    }

    public function assertClear(): void
    {
        $connection = $this->connection();
        $databaseCompany = $connection->selectOne(
            'SELECT public.invumo_current_company_id() AS company_id',
        )?->company_id;

        if ($this->companyId !== null || $connection->transactionLevel() !== 0 || $databaseCompany !== null) {
            throw new LogicException('Tenant context leaked outside its transaction boundary.');
        }
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    private function run(string $companyId, Closure $callback): mixed
    {
        $this->jobExecution->ensureCompany($companyId);

        if ($this->companyId !== null) {
            if ($this->companyId !== $companyId) {
                throw new LogicException('A tenant context cannot switch Company inside an active transaction.');
            }

            return $callback();
        }

        $connection = $this->connection();

        return $connection->transaction(function () use ($companyId, $callback) {
            $this->companyId = $companyId;

            try {
                $this->connection()->selectOne(
                    "SELECT set_config('app.current_company_id', ?, true)",
                    [$companyId],
                );

                return $callback();
            } finally {
                $this->companyId = null;
            }
        });
    }

    private function connection(): ConnectionInterface
    {
        return DB::connection(config('database.tenant_connection'));
    }
}
