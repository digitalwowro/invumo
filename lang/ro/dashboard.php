<?php

return [
    'title' => 'Panou de control',
    'subtitle' => 'Activitatea curentă de facturare și încasare.',
    'view_invoices' => 'Vezi facturile',
    'currency' => [
        'description' => 'Valorile sunt afișate separat pentru această monedă.',
    ],
    'metrics' => [
        'unpaid_invoices' => 'Facturi neplătite',
        'overdue_invoices' => 'Facturi restante',
        'overdue_balance' => 'Sold restant',
        'paid_this_month' => 'Încasat luna aceasta',
        'outstanding_total' => 'Total de încasat',
    ],
    'activity' => [
        'empty_title' => 'Nu există încă activitate de facturare',
        'empty_description' => 'Facturile emise și plățile înregistrate vor apărea aici.',
    ],
    'recent' => [
        'title' => 'Facturi recente',
        'description' => 'Cele mai recente cinci facturi actualizate.',
        'aria_label' => 'Facturi recente',
        'row_label' => 'Deschide factura :number',
        'not_available' => 'Indisponibil',
        'loading' => 'Se încarcă facturile',
        'empty_title' => 'Nu există încă facturi',
        'empty_description' => 'Facturile create vor apărea aici.',
        'no_results_title' => 'Nicio factură găsită',
        'no_results_description' => 'Nicio factură nu corespunde criteriilor curente.',
        'error_title' => 'Facturile nu au putut fi încărcate',
        'error_description' => 'Încearcă din nou.',
        'columns' => [
            'invoice' => 'Factură',
            'dates' => 'Emitere / scadență',
            'total' => 'Total',
            'status' => 'Stare',
            'actions' => 'Acțiuni',
        ],
        'view' => 'Vezi',
    ],
    'statuses' => [
        'DRAFT' => 'Ciornă',
        'ISSUED' => 'Emisă',
        'CANCELLED' => 'Anulată',
        'UNPAID' => 'Neplătită',
        'PARTIALLY_PAID' => 'Plătită parțial',
        'PAID' => 'Plătită',
        'OVERDUE' => 'Restantă',
    ],
];
