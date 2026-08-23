<?php

namespace App\Modules\Audit\Data;

enum AuditActorType: string
{
    case User = 'USER';
    case PublicCustomer = 'PUBLIC_CUSTOMER';
    case ProviderWebhook = 'PROVIDER_WEBHOOK';
    case ScheduledJob = 'SCHEDULED_JOB';
    case System = 'SYSTEM';
}
