<?php

return [
    'management' => [
        'title' => 'Secure public link',
        'description' => 'Share the current document without exposing Company or document identifiers.',
        'statuses' => [
            'ACTIVE' => 'Active',
            'DISABLED' => 'Disabled',
            'EXPIRED' => 'Expired',
            'NOT_CREATED' => 'Not created',
        ],
        'expires' => 'Expires: :date',
        'copy' => 'Copy link',
        'copied' => 'Secure link copied.',
        'copy_failed' => 'The secure link could not be copied.',
        'create' => 'Create secure link',
        're_enable' => 'Create a new secure link',
        'regenerate' => 'Regenerate link',
        'revoke' => 'Revoke link',
    ],
    'page' => [
        'head_title' => 'Shared document',
        'description' => 'This is the current shared document.',
        'download_pdf' => 'Download PDF',
        'provided_by' => 'Securely shared with Invumo',
    ],
    'feedback' => [
        'created' => 'Secure public link created.',
        'regenerated' => 'Secure public link regenerated.',
        'revoked' => 'Secure public link revoked.',
    ],
    'errors' => [
        'unavailable' => 'The secure public link is no longer available. Create a new link first.',
    ],
];
