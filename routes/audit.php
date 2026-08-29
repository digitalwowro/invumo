<?php

use App\Modules\Audit\Http\Controllers\CompanyAuditController;
use Illuminate\Support\Facades\Route;

Route::get('companies/{company}/settings/audit', CompanyAuditController::class)
    ->name('company-audit.index');
