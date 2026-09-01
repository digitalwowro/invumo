<?php

use App\Modules\Catalog\Http\Controllers\InlineProductServiceController;
use App\Modules\Customers\Http\Controllers\InlineCustomerController;
use App\Modules\Delivery\Http\Controllers\DocumentDeliveryController;
use App\Modules\Delivery\Http\Controllers\DocumentPublicLinkController;
use App\Modules\Delivery\Http\Controllers\InvoiceReminderController;
use App\Modules\Delivery\Http\Controllers\PaymentReceivedDeliveryController;
use App\Modules\Invoices\Http\Controllers\InvoiceController;
use App\Modules\Invoices\Http\Controllers\InvoiceDraftController;
use App\Modules\Invoices\Http\Controllers\InvoiceLifecycleController;
use App\Modules\Invoices\Http\Controllers\InvoiceRepresentationController;
use App\Modules\Transactions\Http\Controllers\CompanyTransactionController;
use App\Modules\Transactions\Http\Controllers\InvoiceTransactionController;
use Illuminate\Support\Facades\Route;

Route::get('companies/{company}/invoices', [InvoiceController::class, 'index'])
    ->name('invoices.index');
Route::get('companies/{company}/invoices/create', [InvoiceDraftController::class, 'create'])
    ->name('invoices.create');
Route::get('companies/{company}/transactions', [CompanyTransactionController::class, 'index'])
    ->name('transactions.index');
Route::post('companies/{company}/invoices', [InvoiceDraftController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('invoices.store');
Route::get('companies/{company}/invoices/{invoice}', [InvoiceDraftController::class, 'edit'])
    ->name('invoices.edit');
Route::patch('companies/{company}/invoices/{invoice}', [InvoiceDraftController::class, 'update'])
    ->middleware('throttle:30,1')
    ->name('invoices.update');
Route::delete('companies/{company}/invoices/{invoice}', [InvoiceController::class, 'destroy'])
    ->middleware('throttle:10,1')
    ->name('invoices.destroy');
Route::post('companies/{company}/invoices/{invoice}/issue', [InvoiceLifecycleController::class, 'issue'])
    ->middleware('throttle:20,1')
    ->name('invoices.issue');
Route::post('companies/{company}/invoices/{invoice}/cancel', [InvoiceLifecycleController::class, 'cancel'])
    ->middleware('throttle:20,1')
    ->name('invoices.cancel');
Route::post('companies/{company}/invoices/{invoice}/reopen', [InvoiceLifecycleController::class, 'reopen'])
    ->middleware('throttle:20,1')
    ->name('invoices.reopen');
Route::put('companies/{company}/invoices/{invoice}/reminders', [InvoiceReminderController::class, 'update'])
    ->middleware('throttle:20,1')
    ->name('invoices.reminders.update');
Route::post('companies/{company}/invoices/{invoice}/reminders/{reminder}/retry', [InvoiceReminderController::class, 'retry'])
    ->middleware('throttle:10,1')
    ->name('invoices.reminders.retry');
Route::post('companies/{company}/invoices/{invoice}/transactions', [InvoiceTransactionController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('invoice-transactions.store');
Route::patch('companies/{company}/invoices/{invoice}/transactions/{transaction}', [InvoiceTransactionController::class, 'update'])
    ->middleware('throttle:30,1')
    ->name('invoice-transactions.update');
Route::delete('companies/{company}/invoices/{invoice}/transactions/{transaction}', [InvoiceTransactionController::class, 'destroy'])
    ->middleware('throttle:20,1')
    ->name('invoice-transactions.destroy');
Route::post(
    'companies/{company}/invoices/{invoice}/transactions/{transaction}/payment-received',
    PaymentReceivedDeliveryController::class,
)->middleware('throttle:document-delivery')
    ->name('invoice-transactions.payment-received.store');
Route::get('companies/{company}/invoices/{invoice}/view', [InvoiceRepresentationController::class, 'show'])
    ->name('invoices.current.show');
Route::get('companies/{company}/invoices/{invoice}/pdf', [InvoiceRepresentationController::class, 'pdf'])
    ->middleware('throttle:30,1')
    ->name('invoices.current.pdf');
Route::get('companies/{company}/invoices/{invoice}/logo', [InvoiceRepresentationController::class, 'logo'])
    ->middleware('throttle:60,1')
    ->name('invoices.current.logo');
Route::post('companies/{company}/invoices/{document}/public-link', [DocumentPublicLinkController::class, 'store'])
    ->defaults('document_kind', 'INVOICE')
    ->middleware('throttle:20,1')
    ->name('invoices.public-link.store');
Route::post('companies/{company}/invoices/{document}/public-link/regenerate', [DocumentPublicLinkController::class, 'regenerate'])
    ->defaults('document_kind', 'INVOICE')
    ->middleware('throttle:20,1')
    ->name('invoices.public-link.regenerate');
Route::delete('companies/{company}/invoices/{document}/public-link', [DocumentPublicLinkController::class, 'destroy'])
    ->defaults('document_kind', 'INVOICE')
    ->middleware('throttle:20,1')
    ->name('invoices.public-link.destroy');
Route::post('companies/{company}/invoices/{document}/deliveries', [DocumentDeliveryController::class, 'store'])
    ->defaults('document_kind', 'INVOICE')
    ->middleware('throttle:document-delivery')
    ->name('invoices.deliveries.store');
Route::post('companies/{company}/invoices/{document}/deliveries/{delivery}/retry', [DocumentDeliveryController::class, 'retry'])
    ->defaults('document_kind', 'INVOICE')
    ->middleware('throttle:document-delivery')
    ->name('invoices.deliveries.retry');
Route::post('companies/{company}/invoices/{document}/inline-customers', InlineCustomerController::class)
    ->middleware('throttle:20,1')
    ->name('invoices.inline-customers.store');
Route::post('companies/{company}/invoices/{document}/inline-products', InlineProductServiceController::class)
    ->middleware('throttle:20,1')
    ->name('invoices.inline-products.store');
