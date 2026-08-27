<?php

use App\Modules\Delivery\Http\Controllers\PublicDocumentController;
use App\Modules\Delivery\Http\Controllers\PublicQuoteDecisionController;
use App\Modules\Delivery\Http\Middleware\PublicDocumentResponseHeaders;
use App\Modules\Delivery\Http\Middleware\RedactPublicDocumentToken;
use Illuminate\Support\Facades\Route;

Route::middleware([
    RedactPublicDocumentToken::class,
    PublicDocumentResponseHeaders::class,
    'throttle:public-document-view',
])->group(function (): void {
    Route::get('q/{token}', [PublicDocumentController::class, 'quote'])
        ->name('public-quotes.show');
    Route::get('i/{token}', [PublicDocumentController::class, 'invoice'])
        ->name('public-invoices.show');
});

Route::post('q/{token}/decision', PublicQuoteDecisionController::class)
    ->middleware([
        RedactPublicDocumentToken::class,
        PublicDocumentResponseHeaders::class,
        'throttle:public-document-decision',
    ])
    ->name('public-quotes.decision');

Route::middleware([
    RedactPublicDocumentToken::class,
    PublicDocumentResponseHeaders::class,
    'throttle:public-document-pdf',
])->group(function (): void {
    Route::get('q/{token}/pdf', [PublicDocumentController::class, 'quotePdf'])
        ->name('public-quotes.pdf');
    Route::get('i/{token}/pdf', [PublicDocumentController::class, 'invoicePdf'])
        ->name('public-invoices.pdf');
});
