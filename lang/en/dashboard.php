<?php

return [
    'title' => 'Dashboard',
    'subtitle' => 'Current invoicing and collection activity.',
    'view_invoices' => 'View invoices',
    'currency' => [
        'description' => 'Amounts are shown separately for this currency.',
    ],
    'metrics' => [
        'unpaid_invoices' => 'Unpaid invoices',
        'overdue_invoices' => 'Overdue invoices',
        'overdue_balance' => 'Overdue balance',
        'paid_this_month' => 'Paid this month',
        'outstanding_total' => 'Outstanding total',
    ],
    'activity' => [
        'empty_title' => 'No invoice activity yet',
        'empty_description' => 'Issued invoices and recorded payments will appear here.',
    ],
    'recent' => [
        'title' => 'Recent invoices',
        'description' => 'The five invoices updated most recently.',
        'aria_label' => 'Recent invoices',
        'row_label' => 'Open invoice :number',
        'not_available' => 'Not available',
        'loading' => 'Loading invoices',
        'empty_title' => 'No invoices yet',
        'empty_description' => 'Created invoices will appear here.',
        'no_results_title' => 'No matching invoices',
        'no_results_description' => 'No invoices match the current criteria.',
        'error_title' => 'Invoices could not be loaded',
        'error_description' => 'Please try again.',
        'columns' => [
            'invoice' => 'Invoice',
            'dates' => 'Issue / due',
            'total' => 'Total',
            'status' => 'Status',
            'actions' => 'Actions',
        ],
        'view' => 'View',
    ],
    'statuses' => [
        'DRAFT' => 'Draft',
        'ISSUED' => 'Issued',
        'CANCELLED' => 'Cancelled',
        'UNPAID' => 'Unpaid',
        'PARTIALLY_PAID' => 'Partially paid',
        'PAID' => 'Paid',
        'OVERDUE' => 'Overdue',
    ],
];
