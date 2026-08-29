<?php

namespace App\Modules\Recurring\Actions;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditEvent;
use App\Modules\Audit\Data\AuditActorType;
use App\Modules\Audit\Data\AuditEventData;
use App\Modules\Audit\Data\AuditPayload;
use App\Modules\Catalog\Models\ProductService;
use App\Modules\Companies\Contracts\AuthorizesCompanyActions;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Documents\Actions\LockDocumentConfiguration;
use App\Modules\Recurring\Data\RecurringTemplateState;
use App\Modules\Recurring\Data\UpdateRecurringTemplateData;
use App\Modules\Recurring\Exceptions\RecurringTemplateException;
use App\Modules\Recurring\Models\RecurringTemplate;
use App\Modules\Recurring\Models\RecurringTemplateLine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class UpdateRecurringTemplateDraft
{
    public function __construct(
        private TenantContext $tenantContext,
        private AuthorizesCompanyActions $authorizer,
        private LockDocumentConfiguration $configuration,
        private ResolveLockedRecurringCustomer $customer,
        private LockRecurringTemplateProducts $products,
        private PersistRecurringTemplateLines $persistLines,
        private RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        Company $company,
        User $actor,
        string $templateId,
        UpdateRecurringTemplateData $data,
    ): RecurringTemplate {
        return $this->tenantContext->runForMember(
            $actor,
            $company->id,
            fn (): RecurringTemplate => DB::connection(config('database.tenant_connection'))
                ->transaction(fn (): RecurringTemplate => $this->update(
                    $company, $actor, $templateId, $data,
                ), 3),
        );
    }

    private function update(
        Company $company,
        User $actor,
        string $templateId,
        UpdateRecurringTemplateData $data,
    ): RecurringTemplate {
        $this->authorizer->authorize($actor, $company, CompanyAbility::ManageRecurringDrafts);
        $configuration = $this->configuration->handle();
        $customer = $this->customer->handle(
            $data->customerId,
            $data->customerConfirmationToken,
            $configuration,
        );
        $products = $this->products->handle($data->lines)->keyBy('id');
        $template = RecurringTemplate::query()->whereKey($templateId)->lockForUpdate()->firstOrFail();

        if ($template->state !== RecurringTemplateState::Draft) {
            throw RecurringTemplateException::notDraft();
        }

        if ($template->edit_version !== $data->editVersion) {
            throw RecurringTemplateException::stale();
        }

        $persisted = RecurringTemplateLine::query()
            ->where('recurring_template_id', $template->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $this->assertAvailableProducts($data, $products, $persisted);
        $changedFields = $this->changedFields($template, $data, $persisted->count());
        $complete = $this->persistLines->handle(
            $template->id,
            $persisted,
            $data->lines,
            $customer->currencyPrecision,
        );
        $template->update([
            'internal_name' => $data->internalName,
            'customer_id' => $customer->customerId,
            'customer_reference' => $data->customerReference,
            'edit_version' => $template->edit_version + 1,
        ]);

        $this->recordAuditEvent->handle(new AuditEventData(
            actorType: AuditActorType::User,
            actorUserId: $actor->id,
            action: 'company.recurring_template.draft_updated',
            targetType: 'RecurringTemplate',
            targetId: $template->id,
            after: AuditPayload::fromAllowedFields([
                'changed_fields' => $changedFields,
                'line_count' => count($data->lines),
                'complete_line_count' => $complete,
            ], ['changed_fields', 'line_count', 'complete_line_count']),
        ));

        return $template->refresh();
    }

    /**
     * @param  Collection<array-key, ProductService>  $products
     * @param  Collection<int, RecurringTemplateLine>  $persisted
     */
    private function assertAvailableProducts(
        UpdateRecurringTemplateData $data,
        Collection $products,
        Collection $persisted,
    ): void {
        foreach ($data->lines as $line) {
            $product = $line->productServiceId === null ? null : $products->get($line->productServiceId);
            $stored = $line->id === null ? null : $persisted->firstWhere('id', $line->id);

            if ($product?->archived_at !== null
                && $stored?->product_service_id !== $line->productServiceId) {
                throw RecurringTemplateException::sourceUnavailable();
            }
        }
    }

    /** @return list<string> */
    private function changedFields(
        RecurringTemplate $template,
        UpdateRecurringTemplateData $data,
        int $lineCount,
    ): array {
        $changed = [];

        foreach ([
            'internal_name' => $template->internal_name !== $data->internalName,
            'customer_id' => $template->customer_id !== $data->customerId,
            'customer_reference' => $template->customer_reference !== $data->customerReference,
            'lines' => $lineCount !== count($data->lines) || count($data->lines) > 0,
        ] as $field => $isChanged) {
            if ($isChanged) {
                $changed[] = $field;
            }
        }

        return $changed;
    }
}
