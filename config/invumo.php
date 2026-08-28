<?php

return [
    'default_plan_code' => 'free',
    'company_invitation_lifetime_days' => 7,
    'company_assets' => [
        'disk' => env('COMPANY_ASSETS_DISK', 'company_assets_local'),
        'logo_max_bytes' => 5 * 1024 * 1024,
        'logo_max_width' => 4096,
        'logo_max_height' => 4096,
    ],
    'document_artifacts' => [
        'disk' => env('DOCUMENT_ARTIFACTS_DISK', 'document_artifacts_local'),
    ],
    'document_delivery' => [
        'max_recipients_per_message' => (int) env('DOCUMENT_DELIVERY_MAX_RECIPIENTS', 10),
        'company_recipients_per_hour' => (int) env('DOCUMENT_DELIVERY_COMPANY_RECIPIENTS_PER_HOUR', 50),
        'company_recipients_per_day' => (int) env('DOCUMENT_DELIVERY_COMPANY_RECIPIENTS_PER_DAY', 250),
        'account_recipients_per_hour' => (int) env('DOCUMENT_DELIVERY_ACCOUNT_RECIPIENTS_PER_HOUR', 100),
        'account_recipients_per_day' => (int) env('DOCUMENT_DELIVERY_ACCOUNT_RECIPIENTS_PER_DAY', 500),
        'platform_recipients_per_hour' => (int) env('DOCUMENT_DELIVERY_PLATFORM_RECIPIENTS_PER_HOUR', 1000),
        'platform_recipients_per_day' => (int) env('DOCUMENT_DELIVERY_PLATFORM_RECIPIENTS_PER_DAY', 5000),
    ],
];
