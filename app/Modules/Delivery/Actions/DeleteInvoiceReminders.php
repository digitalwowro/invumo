<?php

namespace App\Modules\Delivery\Actions;

use App\Modules\Delivery\Models\JobDispatch;
use App\Modules\Delivery\Models\ReminderInstance;

final readonly class DeleteInvoiceReminders
{
    public function handle(string $invoiceId): void
    {
        $ids = ReminderInstance::query()
            ->where('invoice_id', $invoiceId)->orderBy('id')->lockForUpdate()->pluck('id');

        if ($ids->isNotEmpty()) {
            JobDispatch::query()->whereIn('target_id', $ids)->delete();
        }
    }
}
