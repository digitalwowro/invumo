<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Platform\Queries\PlatformAccountsPage;
use App\Modules\Platform\Queries\PlatformAuditPage;
use App\Modules\Platform\Queries\PlatformCompaniesPage;
use App\Modules\Platform\Queries\PlatformOverviewPage;
use App\Modules\Platform\Queries\PlatformUsersPage;
use App\Support\Inertia\PlatformTranslationBag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class PlatformPageController
{
    public function overview(
        Request $request,
        PlatformOverviewPage $page,
        PlatformTranslationBag $translations,
    ): Response {
        return $this->render('platform/overview', $request, $page->get(), $translations);
    }

    public function users(
        Request $request,
        PlatformUsersPage $page,
        PlatformTranslationBag $translations,
    ): Response {
        return $this->render('platform/users', $request, $page->for($request), $translations);
    }

    public function accounts(
        Request $request,
        PlatformAccountsPage $page,
        PlatformTranslationBag $translations,
    ): Response {
        return $this->render('platform/accounts', $request, $page->for($request), $translations);
    }

    public function companies(
        Request $request,
        PlatformCompaniesPage $page,
        PlatformTranslationBag $translations,
    ): Response {
        return $this->render('platform/companies', $request, $page->for($request), $translations);
    }

    public function planLifecycle(
        Request $request,
        PlatformAccountsPage $page,
        PlatformTranslationBag $translations,
    ): Response {
        return $this->render(
            'platform/plan-lifecycle',
            $request,
            $page->for($request, lifecycleOnly: true),
            $translations,
        );
    }

    public function audit(
        Request $request,
        PlatformAuditPage $page,
        PlatformTranslationBag $translations,
    ): Response {
        return $this->render('platform/audit', $request, $page->for($request), $translations);
    }

    /** @param array<string, mixed> $props */
    private function render(
        string $component,
        Request $request,
        array $props,
        PlatformTranslationBag $translations,
    ): Response {
        return Inertia::render($component, [
            ...$props,
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
        ]);
    }
}
