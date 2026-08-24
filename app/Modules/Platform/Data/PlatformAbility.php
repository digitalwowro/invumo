<?php

namespace App\Modules\Platform\Data;

enum PlatformAbility: string
{
    case ViewPlatform = 'view_platform';
    case ManageOperators = 'manage_platform_operators';
    case ManageUsers = 'manage_users';
    case ManageAccounts = 'manage_accounts';
    case ViewAudit = 'view_platform_audit';
    case ImpersonateUsers = 'impersonate_users';
}
