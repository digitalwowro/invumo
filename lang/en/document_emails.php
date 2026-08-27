<?php

return [
    'templates' => [
        'QUOTE_SENT' => [
            'subject' => 'Quote {{document_number}} from {{company_name}}',
            'body' => "Hello {{customer_name}},\n\nYour quote {{document_number}} for {{document_total}} is ready and remains valid until {{valid_until}}.\n\nUse the secure link to view and respond.",
            'button_label' => 'View quote',
            'signature' => "Kind regards,\n{{company_name}}",
        ],
        'INVOICE_SENT' => [
            'subject' => 'Invoice {{document_number}} from {{company_name}}',
            'body' => "Hello {{customer_name}},\n\nYour invoice {{document_number}} for {{document_total}} is ready. The outstanding amount is {{outstanding_amount}}, due on {{due_date}}.\n\nUse the secure link to view the invoice.",
            'button_label' => 'View invoice',
            'signature' => "Kind regards,\n{{company_name}}",
        ],
        'PAYMENT_REMINDER' => [
            'subject' => 'Payment reminder for invoice {{document_number}}',
            'body' => "Hello {{customer_name}},\n\nThis is a reminder that {{outstanding_amount}} remains due for invoice {{document_number}}, with a due date of {{due_date}}.\n\nUse the secure link to review the invoice.",
            'button_label' => 'Review invoice',
            'signature' => "Kind regards,\n{{company_name}}",
        ],
        'PAYMENT_RECEIVED' => [
            'subject' => 'Payment received for invoice {{document_number}}',
            'body' => "Hello {{customer_name}},\n\nWe recorded your payment of {{payment_amount}} on {{payment_date}} for invoice {{document_number}}. The remaining outstanding amount is {{outstanding_amount}}.\n\nUse the secure link to view the invoice.",
            'button_label' => 'View invoice',
            'signature' => "Kind regards,\n{{company_name}}",
        ],
    ],
    'preview' => [
        'unavailable' => 'Not available',
        'values' => [
            'company_name' => 'Example Company SRL',
            'customer_name' => 'Ana Popescu',
            'document_number' => 'INV-2026-0042',
            'document_total' => '1,234.56 RON',
            'public_url' => 'https://app.invumo.com/i/example',
            'valid_until' => 'September 30, 2026',
            'due_date' => 'September 30, 2026',
            'outstanding_amount' => '734.56 RON',
            'payment_amount' => '500.00 RON',
            'payment_date' => 'August 27, 2026',
        ],
    ],
];
