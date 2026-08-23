<?php

return [
    'greeting' => 'Hello, :name!',
    'verification' => [
        'subject' => 'Verify your Invumo email',
        'introduction' => 'Confirm this email address to finish setting up your Invumo account.',
        'action' => 'Verify email address',
        'ignore' => 'If you did not create an Invumo account, you can ignore this email.',
    ],
    'recovery' => [
        'subject' => 'Reset your Invumo password',
        'introduction' => 'We received a request to reset the password for your Invumo account.',
        'action' => 'Reset password',
        'expiry' => 'This private link expires in :minutes minutes.',
        'ignore' => 'If you did not request a password reset, you can ignore this email.',
    ],
];
