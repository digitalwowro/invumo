<?php

namespace App\Modules\Companies\Queries;

use App\Foundation\Delivery\EmailAttachmentMode;
use App\Modules\Companies\Models\CompanySetting;

final class CompanyEmailAttachmentDefault
{
    public function get(): EmailAttachmentMode
    {
        return CompanySetting::query()->firstOrFail()->default_email_attachment_mode;
    }
}
