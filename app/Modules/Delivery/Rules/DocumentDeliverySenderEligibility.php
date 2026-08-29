<?php

namespace App\Modules\Delivery\Rules;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMembership;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Models\EmailDelivery;
use App\Modules\Identity\Models\Account;

final class DocumentDeliverySenderEligibility
{
    public function allows(Company $company, Account $account, EmailDelivery $delivery): bool
    {
        if ($company->archived_at !== null || $account->suspended_at !== null) {
            return false;
        }

        if ($delivery->event_type === EmailTemplateEvent::PaymentReminder
            && $delivery->initiated_by_user_id === null) {
            return true;
        }

        if ($delivery->recurring_automatic
            && $delivery->event_type === EmailTemplateEvent::InvoiceSent
            && $delivery->initiated_by_user_id === null) {
            return true;
        }

        $initiator = $delivery->initiated_by_user_id === null
            ? null : User::query()->whereKey($delivery->initiated_by_user_id)->first();

        return $initiator instanceof User
            && $initiator->suspended_at === null
            && CompanyMembership::query()
                ->where('company_id', $company->id)
                ->where('user_id', $initiator->id)
                ->exists();
    }
}
