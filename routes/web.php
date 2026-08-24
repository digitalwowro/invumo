<?php

use App\Modules\Companies\Http\Controllers\CompanyController;
use App\Modules\Companies\Http\Controllers\CompanyDashboardController;
use App\Modules\Companies\Http\Controllers\CompanyInvitationController;
use App\Modules\Companies\Http\Controllers\CompanyLandingController;
use App\Modules\Companies\Http\Controllers\CompanyMemberController;
use App\Modules\Companies\Http\Controllers\CompanyOwnershipController;
use App\Modules\Platform\Http\Controllers\AccountPlanController;
use App\Modules\Platform\Http\Controllers\AccountSuspensionController;
use App\Modules\Platform\Http\Controllers\PlatformPageController;
use App\Modules\Platform\Http\Controllers\UserImpersonationController;
use App\Modules\Platform\Http\Controllers\UserSuspensionController;
use App\Support\Inertia\CommonTranslationBag;
use App\Support\Inertia\DesignSystemTranslationBag;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\ConfirmedPasswordStatusController;

Route::get('/', CompanyLandingController::class)->name('home');

Route::get('invitations/{token}', [CompanyInvitationController::class, 'show'])
    ->middleware('throttle:20,1')
    ->name('company-invitations.show');

Route::middleware('auth')->group(function (): void {
    Route::get('impersonation/suspended', [UserImpersonationController::class, 'suspended'])
        ->name('platform.impersonation.suspended');
    Route::delete('impersonation', [UserImpersonationController::class, 'destroy'])
        ->name('platform.impersonation.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => redirect()->route('home'))->name('dashboard');

    Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('companies/create', [CompanyController::class, 'create'])->name('companies.create');
    Route::post('companies', [CompanyController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('companies.store');

    Route::get('companies/{company}/dashboard', CompanyDashboardController::class)
        ->middleware('company.context')
        ->name('companies.dashboard');

    Route::post('invitations/{token}/accept', [CompanyInvitationController::class, 'accept'])
        ->middleware('throttle:10,1')
        ->name('company-invitations.accept');

    Route::prefix('platform')
        ->name('platform.')
        ->middleware('platform.operator')
        ->group(function (): void {
            Route::get('/', [PlatformPageController::class, 'overview'])->name('overview');
            Route::get('users', [PlatformPageController::class, 'users'])->name('users.index');
            Route::get('accounts', [PlatformPageController::class, 'accounts'])->name('accounts.index');
            Route::get('companies', [PlatformPageController::class, 'companies'])->name('companies.index');
            Route::get('plan-lifecycle', [PlatformPageController::class, 'planLifecycle'])
                ->name('plan-lifecycle.index');
            Route::get('audit', [PlatformPageController::class, 'audit'])->name('audit.index');
            Route::get('password-confirmation', [ConfirmedPasswordStatusController::class, 'show'])
                ->name('password-confirmation.status');
            Route::post('password-confirmation', [ConfirmablePasswordController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('password-confirmation.store');
            Route::post('users/{user}/impersonation', [UserImpersonationController::class, 'store'])
                ->middleware([RequirePassword::class, 'throttle:10,1'])
                ->name('users.impersonation.store');

            Route::post('users/{user}/suspension', [UserSuspensionController::class, 'store'])
                ->middleware([RequirePassword::class, 'throttle:10,1'])
                ->name('users.suspension.store');
            Route::delete('users/{user}/suspension', [UserSuspensionController::class, 'destroy'])
                ->middleware([RequirePassword::class, 'throttle:10,1'])
                ->name('users.suspension.destroy');
            Route::post('accounts/{account}/suspension', [AccountSuspensionController::class, 'store'])
                ->middleware([RequirePassword::class, 'throttle:10,1'])
                ->name('accounts.suspension.store');
            Route::delete('accounts/{account}/suspension', [AccountSuspensionController::class, 'destroy'])
                ->middleware([RequirePassword::class, 'throttle:10,1'])
                ->name('accounts.suspension.destroy');
            Route::patch('accounts/{account}/plan', [AccountPlanController::class, 'update'])
                ->middleware([RequirePassword::class, 'throttle:10,1'])
                ->name('accounts.plan.update');
        });

    Route::middleware('company.context')
        ->scopeBindings()
        ->group(function (): void {
            Route::get('companies/{company}/settings/members', [CompanyMemberController::class, 'index'])
                ->name('company-members.index');
            Route::delete('companies/{company}/settings/members/current', [CompanyMemberController::class, 'leave'])
                ->middleware('throttle:10,1')
                ->name('company-members.leave');
            Route::patch('companies/{company}/settings/ownership', [CompanyOwnershipController::class, 'update'])
                ->middleware([RequirePassword::class, 'throttle:5,1'])
                ->name('company-ownership.update');
            Route::patch('companies/{company}/settings/members/{membership}', [CompanyMemberController::class, 'update'])
                ->middleware('throttle:20,1')
                ->name('company-members.update');
            Route::delete('companies/{company}/settings/members/{membership}', [CompanyMemberController::class, 'destroy'])
                ->middleware('throttle:20,1')
                ->name('company-members.destroy');
            Route::post('companies/{company}/settings/members/invitations', [CompanyMemberController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('company-invitations.store');
            Route::post('companies/{company}/settings/members/invitations/{invitation}/resend', [CompanyInvitationController::class, 'resend'])
                ->middleware('throttle:10,1')
                ->name('company-invitations.resend');
            Route::delete('companies/{company}/settings/members/invitations/{invitation}', [CompanyInvitationController::class, 'revoke'])
                ->middleware('throttle:20,1')
                ->name('company-invitations.revoke');
        });
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
