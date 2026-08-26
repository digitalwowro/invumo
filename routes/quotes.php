<?php

use App\Modules\Catalog\Http\Controllers\CatalogDocumentSourceController;
use App\Modules\Catalog\Http\Controllers\InlineProductServiceController;
use App\Modules\Customers\Http\Controllers\CustomerDocumentSourceController;
use App\Modules\Customers\Http\Controllers\InlineCustomerController;
use App\Modules\Quotes\Http\Controllers\QuoteController;
use App\Modules\Quotes\Http\Controllers\QuoteDraftController;
use App\Modules\Quotes\Http\Controllers\QuoteLifecycleController;
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
Route::delete('companies/{company}/quotes/{quote}', [QuoteController::class, 'destroy'])
    ->middleware('throttle:10,1')
    ->name('quotes.destroy');

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
Route::post('companies/{company}/quotes/{quote}/inline-customers', InlineCustomerController::class)
    ->middleware('throttle:20,1')
    ->name('quotes.inline-customers.store');
Route::post('companies/{company}/quotes/{quote}/inline-products', InlineProductServiceController::class)
    ->middleware('throttle:20,1')
    ->name('quotes.inline-products.store');
