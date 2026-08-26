<?php

use App\Modules\Catalog\Http\Controllers\InlineProductServiceController;
use App\Modules\Customers\Http\Controllers\InlineCustomerController;
use App\Modules\Invoices\Http\Controllers\InvoiceController;
use App\Modules\Invoices\Http\Controllers\InvoiceDraftController;
use App\Modules\Invoices\Http\Controllers\InvoiceRepresentationController;
use Illuminate\Support\Facades\Route;

Route::get('companies/{company}/invoices', [InvoiceController::class, 'index'])
    ->name('invoices.index');
Route::get('companies/{company}/invoices/create', [InvoiceDraftController::class, 'create'])
    ->name('invoices.create');
Route::post('companies/{company}/invoices', [InvoiceDraftController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('invoices.store');
Route::get('companies/{company}/invoices/{invoice}', [InvoiceDraftController::class, 'edit'])
    ->name('invoices.edit');
Route::patch('companies/{company}/invoices/{invoice}', [InvoiceDraftController::class, 'update'])
    ->middleware('throttle:30,1')
    ->name('invoices.update');
Route::get('companies/{company}/invoices/{invoice}/view', [InvoiceRepresentationController::class, 'show'])
    ->name('invoices.current.show');
Route::get('companies/{company}/invoices/{invoice}/pdf', [InvoiceRepresentationController::class, 'pdf'])
    ->middleware('throttle:30,1')
    ->name('invoices.current.pdf');
Route::get('companies/{company}/invoices/{invoice}/logo', [InvoiceRepresentationController::class, 'logo'])
    ->middleware('throttle:60,1')
    ->name('invoices.current.logo');
Route::post('companies/{company}/invoices/{document}/inline-customers', InlineCustomerController::class)
    ->middleware('throttle:20,1')
    ->name('invoices.inline-customers.store');
Route::post('companies/{company}/invoices/{document}/inline-products', InlineProductServiceController::class)
    ->middleware('throttle:20,1')
    ->name('invoices.inline-products.store');
