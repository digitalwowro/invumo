<?php

return [
    'layout' => [
        'title' => 'Settings',
        'description' => 'Manage your profile and account security.',
        'navigationLabel' => 'Settings sections',
        'profile' => 'Profile',
        'security' => 'Security',
    ],
    'shared' => [
        'save' => 'Save changes',
        'cancel' => 'Cancel',
        'password' => 'Password',
        'passwordPlaceholder' => 'Password',
        'showPassword' => 'Show password',
        'hidePassword' => 'Hide password',
    ],
    'pages' => [
        'profile' => [
            'headTitle' => 'Profile settings',
            'title' => 'Profile',
            'description' => 'Update your name and email address.',
            'name' => 'Name',
            'namePlaceholder' => 'Full name',
            'email' => 'Email address',
            'emailPlaceholder' => 'Email address',
            'unverified' => 'Your email address is not verified.',
            'resend' => 'Resend the verification email',
            'verificationSent' => 'A new verification link has been sent to your email address.',
            'deleteTitle' => 'Delete account',
            'deleteDescription' => 'Permanently delete your account and its resources.',
            'warningTitle' => 'Permanent action',
            'warningDescription' => 'This action cannot be undone.',
            'deleteTrigger' => 'Delete account',
            'deleteDialogTitle' => 'Delete your account?',
            'deleteDialogDescription' => 'Your account and its data will be permanently deleted. Enter your password to confirm.',
            'deleteConfirm' => 'Delete account',
            'closeDialog' => 'Close dialog',
        ],
        'security' => [
            'headTitle' => 'Security settings',
            'title' => 'Update password',
            'description' => 'Use a long, unique password to protect your account.',
            'currentPassword' => 'Current password',
            'newPassword' => 'New password',
            'confirmPassword' => 'Confirm password',
        ],
    ],
    'flash' => [
        'profileUpdated' => 'Profile updated.',
        'passwordUpdated' => 'Password updated.',
    ],
];
