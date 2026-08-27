<?php

namespace App\Modules\Companies\Data;

enum CompanyAbility: string
{
    case ViewCompany = 'view_company';
    case ViewCustomers = 'view_customers';
    case ManageCustomers = 'manage_customers';
    case DeleteCustomers = 'delete_customers';
    case ManageCompanySettings = 'manage_company_settings';
    case ManageMembers = 'manage_members';
    case ViewCatalog = 'view_catalog';
    case ManageCatalog = 'manage_catalog';
    case ViewQuotes = 'view_quotes';
    case ManageQuotes = 'manage_quotes';
    case OverrideQuoteConversion = 'override_quote_conversion';
    case UnlinkQuoteInvoice = 'unlink_quote_invoice';
    case DeleteQuotes = 'delete_quotes';
    case ViewInvoices = 'view_invoices';
    case ManageInvoices = 'manage_invoices';
    case DeleteInvoices = 'delete_invoices';
    case ManageNumberCounters = 'manage_number_counters';
    case ManageAdjustments = 'manage_adjustments';
    case ManageRecurringAutomation = 'manage_recurring_automation';
    case ViewOperations = 'view_operations';
    case ViewAudit = 'view_audit';
    case ManageAccount = 'manage_account';
    case TransferOwnership = 'transfer_ownership';
    case DeleteCompany = 'delete_company';
}
