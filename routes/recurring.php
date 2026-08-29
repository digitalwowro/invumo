<?php

use App\Modules\Catalog\Http\Controllers\InlineRecurringProductController;
use App\Modules\Customers\Http\Controllers\InlineRecurringCustomerController;
use App\Modules\Recurring\Http\Controllers\RecurringTemplateController;
use App\Modules\Recurring\Http\Controllers\RecurringTemplateDraftController;
use App\Modules\Recurring\Http\Controllers\RecurringTemplateLifecycleController;
use Illuminate\Support\Facades\Route;

Route::get('companies/{company}/recurring', [RecurringTemplateController::class, 'index'])
    ->name('recurring.index');
Route::get('companies/{company}/recurring/create', [RecurringTemplateDraftController::class, 'create'])
    ->name('recurring.create');
Route::post('companies/{company}/recurring', [RecurringTemplateDraftController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('recurring.store');
Route::get('companies/{company}/recurring/{template}', [RecurringTemplateDraftController::class, 'edit'])
    ->name('recurring.edit');
Route::patch('companies/{company}/recurring/{template}', [RecurringTemplateDraftController::class, 'update'])
    ->middleware('throttle:30,1')
    ->name('recurring.update');
Route::delete('companies/{company}/recurring/{template}', [RecurringTemplateController::class, 'destroy'])
    ->middleware('throttle:10,1')
    ->name('recurring.destroy');
Route::patch('companies/{company}/recurring/{template}/schedule', [RecurringTemplateLifecycleController::class, 'schedule'])
    ->middleware('throttle:30,1')
    ->name('recurring.schedule.update');
Route::post('companies/{company}/recurring/{template}/duplicate', [RecurringTemplateLifecycleController::class, 'duplicate'])
    ->middleware('throttle:20,1')
    ->name('recurring.duplicate');
Route::post('companies/{company}/recurring/{template}/{transition}', [RecurringTemplateLifecycleController::class, 'transition'])
    ->whereIn('transition', ['activate', 'pause', 'resume', 'complete'])
    ->middleware('throttle:20,1')
    ->name('recurring.transition');
Route::post('companies/{company}/recurring/inline-customers', InlineRecurringCustomerController::class)
    ->middleware('throttle:20,1')
    ->name('recurring.inline-customers.store');
Route::post('companies/{company}/recurring/{template}/inline-products', InlineRecurringProductController::class)
    ->middleware('throttle:20,1')
    ->name('recurring.inline-products.store');
