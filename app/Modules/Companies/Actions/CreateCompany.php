<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Companies\Data\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Identity\Models\Account;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class CreateCompany
{
    public function __construct(
        private TenantContext $tenantContext,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Account $account, User $actor, string $name): Company
    {
        if ($account->owner_user_id !== $actor->id) {
            throw new LogicException('Only the Account owner can create its Company.');
        }

        return DB::connection(config('database.tenant_connection'))
            ->transaction(function () use ($account, $actor, $name): Company {
                $company = Company::query()->create([
                    'owning_account_id' => $account->id,
                    'name' => $name,
                ]);

                $company->memberships()->create([
                    'user_id' => $actor->id,
                    'role' => CompanyRole::Owner,
                ]);

                $this->tenantContext->runAsSystem($company->id, function () use ($actor, $company): void {
                    $this->recordAuditEvent->handle(new AuditEventData(
                        actorType: AuditActorType::User,
                        actorUserId: $actor->id,
                        action: 'company.created',
                        targetType: 'Company',
                        targetId: $company->id,
                        after: AuditPayload::fromAllowedFields([
                            'name' => $company->name,
                        ], ['name']),
                    ));
                });

                return $company;
            });
    }
}
