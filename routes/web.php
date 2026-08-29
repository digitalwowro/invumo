<?php

use App\Modules\Catalog\Http\Controllers\ProductServiceController;
use App\Modules\Companies\Http\Controllers\CompanyAppearanceController;
use App\Modules\Companies\Http\Controllers\CompanyBankAccountController;
use App\Modules\Companies\Http\Controllers\CompanyController;
use App\Modules\Companies\Http\Controllers\CompanyDashboardController;
use App\Modules\Companies\Http\Controllers\CompanyDocumentDefaultsController;
use App\Modules\Companies\Http\Controllers\CompanyInvitationController;
use App\Modules\Companies\Http\Controllers\CompanyLandingController;
use App\Modules\Companies\Http\Controllers\CompanyMemberController;
use App\Modules\Companies\Http\Controllers\CompanyNumberSeriesController;
use App\Modules\Companies\Http\Controllers\CompanyOwnershipController;
use App\Modules\Companies\Http\Controllers\CompanySettingsController;
use App\Modules\Companies\Http\Controllers\CompanyTaxPresetController;
use App\Modules\Customers\Http\Controllers\CustomerContactController;
use App\Modules\Customers\Http\Controllers\CustomerController;
use App\Modules\Customers\Http\Controllers\CustomerDefaultsController;
use App\Modules\Customers\Http\Controllers\CustomerDeliveryController;
use App\Modules\Delivery\Http\Controllers\CompanyEmailTemplateController;
use App\Modules\Delivery\Http\Controllers\CompanyReminderRuleController;
use App\Modules\Delivery\Http\Controllers\ZeptoMailWebhookController;
use App\Modules\Documents\Http\Controllers\DocumentNumberCounterController;
use App\Modules\Platform\Http\Controllers\AccountPlanController;
use App\Modules\Platform\Http\Controllers\AccountSuspensionController;
use App\Modules\Platform\Http\Controllers\PlatformPageController;
use App\Modules\Platform\Http\Controllers\UserImpersonationController;
use App\Modules\Platform\Http\Controllers\UserSuspensionController;
use App\Modules\Quotes\Http\Controllers\CustomerPublicDecisionIdentityController;
use App\Support\Inertia\CommonTranslationBag;
use App\Support\Inertia\DesignSystemTranslationBag;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\ConfirmedPasswordStatusController;

Route::get('/', CompanyLandingController::class)->name('home');

require __DIR__.'/public-documents.php';

Route::match(['GET', 'POST'], 'webhooks/zeptomail', ZeptoMailWebhookController::class)
    ->middleware('throttle:zeptomail-webhook')
    ->name('webhooks.zeptomail');

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
            require __DIR__.'/quotes.php';
            require __DIR__.'/invoices.php';

            Route::get('companies/{company}/products', [ProductServiceController::class, 'index'])
                ->name('catalog.index');
            Route::post('companies/{company}/products', [ProductServiceController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('catalog.store');
            Route::patch('companies/{company}/products/{productService}', [ProductServiceController::class, 'update'])
                ->middleware('throttle:20,1')
                ->name('catalog.update');
            Route::post('companies/{company}/products/{productService}/archive', [ProductServiceController::class, 'archive'])
                ->middleware('throttle:20,1')
                ->name('catalog.archive');
            Route::post('companies/{company}/products/{productService}/restore', [ProductServiceController::class, 'restore'])
                ->middleware('throttle:20,1')
                ->name('catalog.restore');
            Route::delete('companies/{company}/products/{productService}', [ProductServiceController::class, 'destroy'])
                ->middleware('throttle:10,1')
                ->name('catalog.destroy');

            Route::get('companies/{company}/customers', [CustomerController::class, 'index'])
                ->name('customers.index');
            Route::get('companies/{company}/customers/create', [CustomerController::class, 'create'])
                ->name('customers.create');
            Route::post('companies/{company}/customers', [CustomerController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('customers.store');
            Route::get('companies/{company}/customers/{customer}/contacts', [CustomerContactController::class, 'index'])
                ->name('customer-contacts.index');
            Route::get('companies/{company}/customers/{customer}/defaults', [CustomerDefaultsController::class, 'index'])
                ->name('customer-defaults.index');
            Route::get('companies/{company}/customers/{customer}', [CustomerController::class, 'show'])
                ->name('customers.show');
            Route::patch('companies/{company}/customers/{customer}', [CustomerController::class, 'update'])
                ->middleware('throttle:20,1')
                ->name('customers.update');
            Route::post('companies/{company}/customers/{customer}/archive', [CustomerController::class, 'archive'])
                ->middleware('throttle:20,1')
                ->name('customers.archive');
            Route::post('companies/{company}/customers/{customer}/restore', [CustomerController::class, 'restore'])
                ->middleware('throttle:20,1')
                ->name('customers.restore');
            Route::delete('companies/{company}/customers/{customer}', [CustomerController::class, 'destroy'])
                ->middleware('throttle:10,1')
                ->name('customers.destroy');
            Route::delete('companies/{company}/customers/{customer}/public-decision-identity', [CustomerPublicDecisionIdentityController::class, 'destroy'])
                ->middleware('throttle:5,1')
                ->name('customer-public-decision-identity.destroy');
            Route::post('companies/{company}/customers/{customer}/contacts', [CustomerContactController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('customer-contacts.store');
            Route::patch('companies/{company}/customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'update'])
                ->middleware('throttle:20,1')
                ->name('customer-contacts.update');
            Route::post('companies/{company}/customers/{customer}/contacts/{contact}/archive', [CustomerContactController::class, 'archive'])
                ->middleware('throttle:20,1')
                ->name('customer-contacts.archive');
            Route::post('companies/{company}/customers/{customer}/contacts/{contact}/restore', [CustomerContactController::class, 'restore'])
                ->middleware('throttle:20,1')
                ->name('customer-contacts.restore');
            Route::delete('companies/{company}/customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'destroy'])
                ->middleware('throttle:10,1')
                ->name('customer-contacts.destroy');
            Route::patch('companies/{company}/customers/{customer}/delivery', [CustomerDeliveryController::class, 'update'])
                ->middleware('throttle:20,1')
                ->name('customer-delivery.update');
            Route::patch('companies/{company}/customers/{customer}/defaults', [CustomerDefaultsController::class, 'update'])
                ->middleware('throttle:20,1')
                ->name('customer-defaults.update');

            Route::get('companies/{company}/settings', [CompanySettingsController::class, 'index'])
                ->name('company-settings.index');
            Route::get('companies/{company}/settings/profile', [CompanySettingsController::class, 'edit'])
                ->name('company-settings.profile.edit');
            Route::patch('companies/{company}/settings/profile', [CompanySettingsController::class, 'update'])
                ->middleware('throttle:20,1')
                ->name('company-settings.profile.update');
            Route::get('companies/{company}/settings/documents', [CompanyDocumentDefaultsController::class, 'edit'])
                ->name('company-document-defaults.edit');
            Route::patch('companies/{company}/settings/documents', [CompanyDocumentDefaultsController::class, 'update'])
                ->middleware('throttle:20,1')
                ->name('company-document-defaults.update');
            Route::get('companies/{company}/settings/email-templates', [CompanyEmailTemplateController::class, 'index'])
                ->name('company-email-templates.index');
            Route::put('companies/{company}/settings/email-templates', [CompanyEmailTemplateController::class, 'update'])
                ->middleware('throttle:20,1')
                ->name('company-email-templates.update');
            Route::post('companies/{company}/settings/email-templates/preview', [CompanyEmailTemplateController::class, 'preview'])
                ->middleware('throttle:60,1')
                ->name('company-email-templates.preview');
            Route::delete('companies/{company}/settings/email-templates/{event}/{language}', [CompanyEmailTemplateController::class, 'destroy'])
                ->middleware('throttle:20,1')
                ->name('company-email-templates.destroy');
            Route::get('companies/{company}/settings/reminders', [CompanyReminderRuleController::class, 'index'])
                ->name('company-reminder-rules.index');
            Route::put('companies/{company}/settings/reminders', [CompanyReminderRuleController::class, 'update'])
                ->middleware('throttle:20,1')
                ->name('company-reminder-rules.update');
            Route::get('companies/{company}/settings/numbering', [CompanyNumberSeriesController::class, 'edit'])
                ->name('company-number-series.edit');
            Route::patch('companies/{company}/settings/numbering', [CompanyNumberSeriesController::class, 'update'])
                ->middleware('throttle:20,1')
                ->name('company-number-series.update');
            Route::patch('companies/{company}/settings/numbering/counters/{counter}', [DocumentNumberCounterController::class, 'update'])
                ->middleware('throttle:10,1')
                ->name('company-number-counters.update');
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
            Route::get('companies/{company}/settings/appearance', [CompanyAppearanceController::class, 'edit'])
                ->name('company-appearance.edit');
            Route::post('companies/{company}/settings/appearance', [CompanyAppearanceController::class, 'update'])
                ->middleware('throttle:20,1')
                ->name('company-appearance.update');
            Route::get('companies/{company}/settings/appearance/logo', [CompanyAppearanceController::class, 'logo'])
                ->middleware('throttle:60,1')
                ->name('company-appearance.logo');
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
