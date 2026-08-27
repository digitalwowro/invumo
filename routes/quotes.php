<?php

use App\Modules\Catalog\Http\Controllers\CatalogDocumentSourceController;
use App\Modules\Catalog\Http\Controllers\InlineProductServiceController;
use App\Modules\Customers\Http\Controllers\CustomerDocumentSourceController;
use App\Modules\Customers\Http\Controllers\InlineCustomerController;
use App\Modules\Delivery\Http\Controllers\DocumentPublicLinkController;
use App\Modules\Quotes\Http\Controllers\QuoteController;
use App\Modules\Quotes\Http\Controllers\QuoteDraftController;
use App\Modules\Quotes\Http\Controllers\QuoteInvoiceController;
use App\Modules\Quotes\Http\Controllers\QuoteLifecycleController;
use App\Modules\Quotes\Http\Controllers\QuoteRepresentationController;
use Illuminate\Support\Facades\Route;

Route::get('companies/{company}/quotes', [QuoteController::class, 'index'])
    ->name('quotes.index');
Route::get('companies/{company}/quotes/create', [QuoteDraftController::class, 'create'])
    ->name('quotes.create');
Route::post('companies/{company}/quotes', [QuoteDraftController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('quotes.store');
Route::get('companies/{company}/quotes/{quote}', [QuoteDraftController::class, 'edit'])
    ->name('quotes.edit');
Route::patch('companies/{company}/quotes/{quote}', [QuoteDraftController::class, 'update'])
    ->middleware('throttle:30,1')
    ->name('quotes.update');
Route::patch('companies/{company}/quotes/{quote}/lifecycle', [QuoteLifecycleController::class, 'update'])
    ->middleware('throttle:20,1')
    ->name('quotes.lifecycle.update');
Route::post('companies/{company}/quotes/{quote}/invoices', [QuoteInvoiceController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('quotes.invoices.store');
Route::post('companies/{company}/quotes/{quote}/invoices/{invoice}/unlink', [QuoteInvoiceController::class, 'unlink'])
    ->middleware('throttle:10,1')
    ->name('quotes.invoices.unlink');
Route::delete('companies/{company}/quotes/{quote}', [QuoteController::class, 'destroy'])
    ->middleware('throttle:10,1')
    ->name('quotes.destroy');
Route::get('companies/{company}/quotes/{quote}/view', [QuoteRepresentationController::class, 'show'])
    ->name('quotes.current.show');
Route::get('companies/{company}/quotes/{quote}/pdf', [QuoteRepresentationController::class, 'pdf'])
    ->middleware('throttle:30,1')
    ->name('quotes.current.pdf');
Route::get('companies/{company}/quotes/{quote}/logo', [QuoteRepresentationController::class, 'logo'])
    ->middleware('throttle:60,1')
    ->name('quotes.current.logo');
Route::post('companies/{company}/quotes/{document}/public-link', [DocumentPublicLinkController::class, 'store'])
    ->defaults('document_kind', 'QUOTE')
    ->middleware('throttle:20,1')
    ->name('quotes.public-link.store');
Route::post('companies/{company}/quotes/{document}/public-link/regenerate', [DocumentPublicLinkController::class, 'regenerate'])
    ->defaults('document_kind', 'QUOTE')
    ->middleware('throttle:20,1')
    ->name('quotes.public-link.regenerate');
Route::delete('companies/{company}/quotes/{document}/public-link', [DocumentPublicLinkController::class, 'destroy'])
    ->defaults('document_kind', 'QUOTE')
    ->middleware('throttle:20,1')
    ->name('quotes.public-link.destroy');

Route::get('companies/{company}/document-sources/customers', [CustomerDocumentSourceController::class, 'index'])
    ->name('quote-sources.customers.index');
Route::get('companies/{company}/document-sources/company-customer-defaults', [CustomerDocumentSourceController::class, 'companyDefaults'])
    ->name('quote-sources.customers.company-defaults');
Route::get('companies/{company}/document-sources/customers/{customer}', [CustomerDocumentSourceController::class, 'show'])
    ->name('quote-sources.customers.show');
Route::get('companies/{company}/document-sources/products', [CatalogDocumentSourceController::class, 'index'])
    ->name('quote-sources.products.index');
Route::get('companies/{company}/document-sources/products/{product}', [CatalogDocumentSourceController::class, 'show'])
    ->name('quote-sources.products.show');
Route::post('companies/{company}/quotes/{document}/inline-customers', InlineCustomerController::class)
    ->middleware('throttle:20,1')
    ->name('quotes.inline-customers.store');
Route::post('companies/{company}/quotes/{document}/inline-products', InlineProductServiceController::class)
    ->middleware('throttle:20,1')
    ->name('quotes.inline-products.store');
