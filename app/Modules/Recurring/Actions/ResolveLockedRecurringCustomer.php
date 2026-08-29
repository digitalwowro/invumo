<?php

namespace App\Modules\Recurring\Actions;

use App\Modules\Customers\Data\ResolvedDocumentCustomer;
use App\Modules\Customers\Queries\ResolveDocumentCustomer;
use App\Modules\Documents\Data\LockedDocumentConfiguration;
use App\Modules\Recurring\Exceptions\RecurringTemplateException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class ResolveLockedRecurringCustomer
{
    public function __construct(private ResolveDocumentCustomer $customers) {}

    public function handle(
        string $customerId,
        string $confirmationToken,
        LockedDocumentConfiguration $configuration,
    ): ResolvedDocumentCustomer {
        try {
            $customer = $this->customers->forLocked($customerId, $configuration);
        } catch (ModelNotFoundException) {
            throw RecurringTemplateException::sourceUnavailable();
        }

        if (! hash_equals($customer->confirmationToken, $confirmationToken)) {
            throw RecurringTemplateException::customerDefaultsChanged();
        }

        return $customer;
    }
}
