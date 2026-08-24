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
];
