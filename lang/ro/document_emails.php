<?php

return [
    'templates' => [
        'QUOTE_SENT' => [
            'subject' => 'Oferta {{document_number}} de la {{company_name}}',
            'body' => "Bună ziua, {{customer_name}},\n\nOferta {{document_number}}, în valoare de {{document_total}}, este pregătită și rămâne valabilă până la {{valid_until}}.\n\nFolosiți linkul securizat pentru a o consulta și pentru a răspunde.",
            'button_label' => 'Vezi oferta',
            'signature' => "Cu stimă,\n{{company_name}}",
        ],
        'INVOICE_SENT' => [
            'subject' => 'Factura {{document_number}} de la {{company_name}}',
            'body' => "Bună ziua, {{customer_name}},\n\nFactura {{document_number}}, în valoare de {{document_total}}, este pregătită. Suma restantă este {{outstanding_amount}}, cu scadența la {{due_date}}.\n\nFolosiți linkul securizat pentru a consulta factura.",
            'button_label' => 'Vezi factura',
            'signature' => "Cu stimă,\n{{company_name}}",
        ],
        'PAYMENT_REMINDER' => [
            'subject' => 'Notificare de plată pentru factura {{document_number}}',
            'body' => "Bună ziua, {{customer_name}},\n\nVă reamintim că suma de {{outstanding_amount}} aferentă facturii {{document_number}} este restantă, cu scadența la {{due_date}}.\n\nFolosiți linkul securizat pentru a consulta factura.",
            'button_label' => 'Consultă factura',
            'signature' => "Cu stimă,\n{{company_name}}",
        ],
        'PAYMENT_RECEIVED' => [
            'subject' => 'Plată înregistrată pentru factura {{document_number}}',
            'body' => "Bună ziua, {{customer_name}},\n\nAm înregistrat plata de {{payment_amount}} din {{payment_date}} pentru factura {{document_number}}. Suma restantă rămasă este {{outstanding_amount}}.\n\nFolosiți linkul securizat pentru a consulta factura.",
            'button_label' => 'Vezi factura',
            'signature' => "Cu stimă,\n{{company_name}}",
        ],
    ],
    'preview' => [
        'unavailable' => 'Indisponibil',
        'values' => [
            'company_name' => 'Compania Exemplu SRL',
            'customer_name' => 'Ana Popescu',
            'document_number' => 'FAC-2026-0042',
            'document_total' => '1.234,56 RON',
            'public_url' => 'https://app.invumo.com/i/exemplu',
            'valid_until' => '30 septembrie 2026',
            'due_date' => '30 septembrie 2026',
            'outstanding_amount' => '734,56 RON',
            'payment_amount' => '500,00 RON',
            'payment_date' => '27 august 2026',
        ],
    ],
];
