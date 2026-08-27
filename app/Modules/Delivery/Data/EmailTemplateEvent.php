<?php

namespace App\Modules\Delivery\Data;

enum EmailTemplateEvent: string
{
    case QuoteSent = 'QUOTE_SENT';
    case InvoiceSent = 'INVOICE_SENT';
    case PaymentReminder = 'PAYMENT_REMINDER';
    case PaymentReceived = 'PAYMENT_RECEIVED';
}
