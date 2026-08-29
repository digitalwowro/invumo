<?php

namespace App\Modules\Companies\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordDataErasure;
use App\Modules\Audit\Data\DataErasureAction;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Data\CompanyErasureState;
use App\Modules\Companies\Data\EraseCompanyData;
use App\Modules\Companies\Data\ErasedCompanyFile;
use App\Modules\Companies\Exceptions\CompanyErasureException;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyAsset;
use App\Modules\Companies\Policies\CompanyActionAuthorizer;
use App\Modules\Delivery\Actions\PrepareCompanyDeliveryErasure;
use Illuminate\Support\Facades\DB;

final readonly class EraseCompany
{
    public function __construct(
        private TenantContext $tenantContext,
        private CompanyActionAuthorizer $authorizer,
        private PrepareCompanyDeliveryErasure $deliveryErasure,
        private RecordDataErasure $recordErasure,
        private RecordCompanyErasureFiles $recordFiles,
        private QueueCompanyErasureFileCleanup $queueFileCleanup,
    ) {}

    public function handle(Company $company, User $actor, EraseCompanyData $data): void
    {
        $erasureEventId = $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): ?string => DB::connection(config('database.tenant_connection'))->transaction(
                fn (): ?string => $this->erase($company, $actor, $data),
                3,
            ),
        );

        if ($erasureEventId !== null) {
            $this->queueFileCleanup->handle($erasureEventId);
        }
    }

    private function erase(Company $company, User $actor, EraseCompanyData $data): ?string
    {
        $this->authorizer->authorize($actor, $company, CompanyAbility::DeleteCompany);
        $lockedCompany = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
        $delivery = $this->deliveryErasure->handle();
        $state = new CompanyErasureState(
            $lockedCompany->name,
            $delivery->pendingSubmissionCount,
        );

        if (! hash_equals($state->version(), $data->stateVersion)) {
            throw CompanyErasureException::stateChanged();
        }

        if ($state->blocked()) {
            throw CompanyErasureException::deliveryInProgress();
        }

        if (! $data->confirmed || ! $data->confirmedHighRisk) {
            throw CompanyErasureException::confirmationRequired();
        }

        if (! hash_equals($lockedCompany->name, $data->confirmationName)) {
            throw CompanyErasureException::nameConfirmationInvalid();
        }

        $assets = CompanyAsset::query()->orderBy('id')->lockForUpdate()->get();
        $files = [
            ...$assets->map(fn (CompanyAsset $asset): ErasedCompanyFile => new ErasedCompanyFile(
                $asset->storage_disk,
                $asset->storage_key,
            ))->all(),
            ...array_map(fn ($file): ErasedCompanyFile => new ErasedCompanyFile(
                $file->disk,
                $file->key,
            ), $delivery->files),
        ];

        $event = $this->recordErasure->handle(
            DataErasureAction::CompanyErased,
            $lockedCompany->id,
            $actor->id,
        );
        $fileCount = $this->recordFiles->handle($event->id, $files);
        $lockedCompany->delete();

        return $fileCount > 0 ? $event->id : null;
    }
}
