<?php

return [
    'pages' => [
        403 => [
            'headTitle' => 'Access denied',
            'title' => 'You cannot access this page',
            'description' => 'Your current Invumo access does not include this action or page.',
            'action' => 'Return to Invumo',
        ],
        404 => [
            'headTitle' => 'Page not found',
            'title' => 'We could not find that page',
            'description' => 'The address may be incorrect, or the page may no longer be available.',
            'action' => 'Return to Invumo',
        ],
        500 => [
            'headTitle' => 'Something went wrong',
            'title' => 'Invumo could not complete that request',
            'description' => 'Please try again. If the problem continues, contact support.',
            'action' => 'Return to Invumo',
        ],
        503 => [
            'headTitle' => 'Temporarily unavailable',
            'title' => 'Invumo is temporarily unavailable',
            'description' => 'Please wait a moment and try again.',
            'action' => 'Return to Invumo',
        ],
    ],
];
