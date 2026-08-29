<?php

namespace App\Modules\Delivery\Rules;

use App\Modules\Delivery\Data\EmailTemplateFieldLimits;
use App\Modules\Delivery\Data\RenderedEmailTemplate;
use App\Modules\Delivery\Exceptions\DocumentDeliveryException;

final class ValidateResolvedEmailContent
{
    public function handle(RenderedEmailTemplate $content): void
    {
        foreach ([
            'subject' => [$content->subject, EmailTemplateFieldLimits::SUBJECT],
            'body' => [$content->body, EmailTemplateFieldLimits::BODY],
            'button_label' => [$content->buttonLabel, EmailTemplateFieldLimits::BUTTON_LABEL],
            'signature' => [$content->signature, EmailTemplateFieldLimits::SIGNATURE],
        ] as $field => [$value, $limit]) {
            if (is_string($value) && mb_strlen($value) > $limit) {
                throw DocumentDeliveryException::resolvedContentTooLong($field);
            }
        }
    }
}
