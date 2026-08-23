<?php

namespace App\Modules\Companies\Data;

enum CompanyAbility: string
{
    case ViewCompany = 'view_company';
    case ManageCompanySettings = 'manage_company_settings';
    case ManageMembers = 'manage_members';
    case ManageCatalog = 'manage_catalog';
    case ManageAdjustments = 'manage_adjustments';
    case ManageRecurringAutomation = 'manage_recurring_automation';
    case ViewOperations = 'view_operations';
    case ViewAudit = 'view_audit';
    case ManageAccount = 'manage_account';
    case TransferOwnership = 'transfer_ownership';
    case DeleteCompany = 'delete_company';
}
