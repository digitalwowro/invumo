<?php

return [
    'head_title' => 'Tranzacții',
    'title' => 'Tranzacții',
    'description' => 'Verifică Plățile, Rambursările și Ajustările înregistrate de Companie pentru toate Facturile.',
    'search_placeholder' => 'Caută Factură, Client, metodă sau referință',
    'date_from' => 'Data tranzacției de la',
    'date_to' => 'Data tranzacției până la',
    'date_label' => 'Data tranzacției',
    'kind_label' => 'Tipul tranzacției',
    'loading' => 'Se încarcă tranzacțiile',
    'empty_title' => 'Nicio tranzacție încă',
    'empty_description' => 'Plățile, Rambursările și Ajustările înregistrate pe Facturi vor apărea aici.',
    'no_results_title' => 'Nicio tranzacție nu corespunde',
    'no_results_description' => 'Modifică sau șterge filtrele curente.',
    'error_title' => 'Tranzacțiile nu au putut fi încărcate',
    'error_description' => 'Încearcă din nou.',
    'columns' => [
        'date' => 'Dată',
        'invoice' => 'Factură',
        'type' => 'Tip',
        'amount' => 'Sumă',
        'details' => 'Metodă / referință',
        'open' => 'Deschide Factura',
    ],
    'kind_options' => [
        'all' => 'Toate tipurile de tranzacție',
        'PAYMENT' => 'Plăți',
        'REFUND' => 'Rambursări',
        'ADJUSTMENT' => 'Ajustări',
    ],
    'kinds' => [
        'PAYMENT' => 'Plată',
        'REFUND' => 'Rambursare',
        'ADJUSTMENT' => 'Ajustare',
    ],
    'directions' => [
        'INCREASE_PAID' => 'Crește suma plătită',
        'DECREASE_PAID' => 'Scade suma plătită',
    ],
    'sort_options' => [
        'date_desc' => 'Cea mai nouă dată a tranzacției',
        'date_asc' => 'Cea mai veche dată a tranzacției',
        'recent' => 'Înregistrate recent',
    ],
    'date_presets' => [
        'any' => 'Oricare',
        'this_month' => 'Luna aceasta',
        'last_ninety_days' => 'Ultimele 90 de zile',
    ],
    'summary' => [
        'aria_label' => 'Prezentare generală a tranzacțiilor',
        'all' => 'Toate tranzacțiile',
        'payments' => 'Plăți',
        'refunds' => 'Rambursări',
        'adjustments' => 'Ajustări',
    ],
];
