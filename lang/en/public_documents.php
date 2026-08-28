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
    'decision' => [
        'title' => 'Respond to this quote',
        'description' => 'Enter your details, then accept or reject the current quote.',
        'customer_name' => 'Your name',
        'customer_email' => 'Your email address',
        'accept' => 'Accept quote',
        'reject' => 'Reject quote',
        'accepted_title' => 'Quote accepted',
        'accepted_description' => 'Your acceptance has been recorded.',
        'rejected_title' => 'Quote rejected',
        'rejected_description' => 'Your rejection has been recorded.',
        'unavailable_title' => 'A response is not available',
        'unavailable_description' => 'This quote cannot currently be accepted or rejected.',
    ],
    'feedback' => [
        'created' => 'Secure public link created.',
        'regenerated' => 'Secure public link regenerated.',
        'revoked' => 'Secure public link revoked.',
    ],
    'errors' => [
        'unavailable' => 'The secure public link is no longer available. Create a new link first.',
        'decision_unavailable' => 'This quote can no longer receive a public response.',
        'decision_delivery_pending' => 'This quote is currently being sent. Wait a moment, then try again.',
        'decision_conflict' => 'A different response has already been recorded. Contact the Company if it needs correction.',
        'idempotency_conflict' => 'This response could not be safely retried. Reload the page and try again.',
    ],
];
