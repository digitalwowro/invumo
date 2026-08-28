<?php

namespace App\Modules\Invoices\Data;

enum InvoiceIssueFailure: string
{
    case Stale = 'stale';
    case Incomplete = 'issue_incomplete';
    case Unavailable = 'lifecycle_unavailable';
}
