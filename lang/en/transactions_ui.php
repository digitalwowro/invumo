<?php

return [
    'head_title' => 'Transactions',
    'title' => 'Transactions',
    'description' => 'Review the Company’s recorded Payments, Refunds, and Adjustments across all Invoices.',
    'search_placeholder' => 'Search Invoice, Customer, method, or reference',
    'date_from' => 'Transaction date from',
    'date_to' => 'Transaction date to',
    'date_label' => 'Transaction date',
    'kind_label' => 'Transaction type',
    'loading' => 'Loading transactions',
    'empty_title' => 'No transactions yet',
    'empty_description' => 'Payments, Refunds, and Adjustments recorded on Invoices will appear here.',
    'no_results_title' => 'No transactions match',
    'no_results_description' => 'Change or clear the current filters.',
    'error_title' => 'Transactions could not be loaded',
    'error_description' => 'Try again.',
    'columns' => [
        'date' => 'Date',
        'invoice' => 'Invoice',
        'type' => 'Type',
        'amount' => 'Amount',
        'details' => 'Method / reference',
        'open' => 'Open Invoice',
    ],
    'kind_options' => [
        'all' => 'All transaction types',
        'PAYMENT' => 'Payments',
        'REFUND' => 'Refunds',
        'ADJUSTMENT' => 'Adjustments',
    ],
    'kinds' => [
        'PAYMENT' => 'Payment',
        'REFUND' => 'Refund',
        'ADJUSTMENT' => 'Adjustment',
    ],
    'directions' => [
        'INCREASE_PAID' => 'Increase paid',
        'DECREASE_PAID' => 'Decrease paid',
    ],
    'sort_options' => [
        'date_desc' => 'Newest transaction date',
        'date_asc' => 'Oldest transaction date',
        'recent' => 'Recently recorded',
    ],
    'date_presets' => [
        'any' => 'Any',
        'this_month' => 'This month',
        'last_ninety_days' => 'Last 90 days',
    ],
    'summary' => [
        'aria_label' => 'Transaction overview',
        'all' => 'All transactions',
        'payments' => 'Payments',
        'refunds' => 'Refunds',
        'adjustments' => 'Adjustments',
    ],
];
