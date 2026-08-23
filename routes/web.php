<?php

use App\Support\Inertia\CommonTranslationBag;
use App\Support\Inertia\DashboardTranslationBag;
use App\Support\Inertia\DesignSystemTranslationBag;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'))
    ->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn (DashboardTranslationBag $translations) => Inertia::render('dashboard', [
        'translations' => $translations->toArray(),
    ]))->name('dashboard');
});

if (app()->environment(['local', 'testing'])) {
    Route::get('__design-system/{locale}', function (
        string $locale,
        DesignSystemTranslationBag $translations,
        CommonTranslationBag $common,
    ) {
        abort_unless(in_array($locale, config('localization.supported_locales'), true), 404);

        app()->setLocale($locale);

        return Inertia::render('design-system/gallery', [
            'gallery' => $translations->toArray($locale),
            'i18n' => [
                'locale' => $locale,
                'supportedLocales' => config('localization.supported_locales'),
                'common' => $common->toArray($locale),
            ],
        ]);
    })->name('design-system.gallery');
}

require __DIR__.'/settings.php';
