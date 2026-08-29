<?php

namespace App\Modules\Recurring\Actions;

use App\Modules\Documents\Data\DocumentDraftFailure;
use App\Modules\Documents\Data\DocumentLineFailure;
use App\Modules\Invoices\Data\ScheduledInvoiceFailure;
use App\Modules\Recurring\Data\RecurringJobResult;
use App\Modules\Recurring\Exceptions\RecurringTemplateException;
use InvalidArgumentException;

final readonly class ExecuteRecurringGeneration
{
    public function __construct(
        private GenerateDueRecurringInvoices $generate,
        private FailRecurringGeneration $fail,
    ) {}

    public function handle(
        string $companyId,
        string $dispatchId,
        int $attempt,
    ): RecurringJobResult {
        try {
            return $this->generate->handle($companyId, $dispatchId, $attempt) > 0
                ? RecurringJobResult::Generated
                : RecurringJobResult::NoWork;
        } catch (RecurringTemplateException $exception) {
            return $this->permanent($companyId, $dispatchId, $attempt, $exception->reason());
        } catch (DocumentLineFailure) {
            return $this->permanent($companyId, $dispatchId, $attempt, 'line_invalid');
        } catch (DocumentDraftFailure $exception) {
            return $this->permanent($companyId, $dispatchId, $attempt, $exception->reason());
        } catch (ScheduledInvoiceFailure $exception) {
            return $this->permanent($companyId, $dispatchId, $attempt, $exception->reason());
        } catch (InvalidArgumentException) {
            return $this->permanent($companyId, $dispatchId, $attempt, 'schedule_invalid');
        }
    }

    private function permanent(
        string $companyId,
        string $dispatchId,
        int $attempt,
        string $category,
    ): RecurringJobResult {
        $this->fail->handle($companyId, $dispatchId, $attempt, $category);

        return RecurringJobResult::PermanentFailure;
    }
}
