<?php

use App\Modules\Companies\Http\Controllers\CompanyBankAccountController;
use App\Modules\Companies\Http\Controllers\CompanyTaxPresetController;
use Illuminate\Support\Facades\Route;

Route::get('companies/{company}/settings/taxes', [CompanyTaxPresetController::class, 'index'])
    ->name('company-tax-presets.index');
Route::post('companies/{company}/settings/taxes', [CompanyTaxPresetController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('company-tax-presets.store');
Route::patch('companies/{company}/settings/taxes/{taxPreset}', [CompanyTaxPresetController::class, 'update'])
    ->middleware('throttle:20,1')
    ->name('company-tax-presets.update');
Route::patch('companies/{company}/settings/taxes/{taxPreset}/archive', [CompanyTaxPresetController::class, 'archive'])
    ->middleware('throttle:20,1')
    ->name('company-tax-presets.archive');
Route::patch('companies/{company}/settings/taxes/{taxPreset}/restore', [CompanyTaxPresetController::class, 'restore'])
    ->middleware('throttle:20,1')
    ->name('company-tax-presets.restore');
Route::delete('companies/{company}/settings/taxes/{taxPreset}', [CompanyTaxPresetController::class, 'destroy'])
    ->middleware('throttle:20,1')
    ->name('company-tax-presets.destroy');

Route::get('companies/{company}/settings/bank-accounts', [CompanyBankAccountController::class, 'index'])
    ->name('company-bank-accounts.index');
Route::post('companies/{company}/settings/bank-accounts', [CompanyBankAccountController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('company-bank-accounts.store');
Route::patch('companies/{company}/settings/bank-accounts/{bankAccount}', [CompanyBankAccountController::class, 'update'])
    ->middleware('throttle:20,1')
    ->name('company-bank-accounts.update');
Route::patch('companies/{company}/settings/bank-accounts/{bankAccount}/archive', [CompanyBankAccountController::class, 'archive'])
    ->middleware('throttle:20,1')
    ->name('company-bank-accounts.archive');
Route::patch('companies/{company}/settings/bank-accounts/{bankAccount}/restore', [CompanyBankAccountController::class, 'restore'])
    ->middleware('throttle:20,1')
    ->name('company-bank-accounts.restore');
Route::delete('companies/{company}/settings/bank-accounts/{bankAccount}', [CompanyBankAccountController::class, 'destroy'])
    ->middleware('throttle:20,1')
    ->name('company-bank-accounts.destroy');
