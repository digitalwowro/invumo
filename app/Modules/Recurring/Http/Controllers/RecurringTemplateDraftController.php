<?php

namespace App\Modules\Recurring\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Documents\Data\DocumentLineFailure;
use App\Modules\Recurring\Actions\CreateRecurringTemplateDraft;
use App\Modules\Recurring\Actions\UpdateRecurringTemplateDraft;
use App\Modules\Recurring\Exceptions\RecurringTemplateException;
use App\Modules\Recurring\Http\Requests\CreateRecurringTemplateRequest;
use App\Modules\Recurring\Http\Requests\UpdateRecurringTemplateRequest;
use App\Modules\Recurring\Queries\RecurringTemplateDraftPage;
use App\Support\Inertia\CatalogUiTranslationBag;
use App\Support\Inertia\CustomersUiTranslationBag;
use App\Support\Inertia\RecurringUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class RecurringTemplateDraftController extends Controller
{
    public function create(
        Request $request,
        Company $company,
        RecurringTemplateDraftPage $page,
        RecurringUiTranslationBag $translations,
        CustomersUiTranslationBag $customerTranslations,
    ): Response {
        return Inertia::render('recurring/create', [
            ...$page->create(
                $company,
                $request->user(),
                app()->getLocale(),
                $request->session()->pull('inline_customer_id'),
            ),
            'translations' => $translations->toArray(),
            'customerTranslations' => $customerTranslations->toArray(),
        ]);
    }

    public function store(
        CreateRecurringTemplateRequest $request,
        Company $company,
        CreateRecurringTemplateDraft $create,
    ): RedirectResponse {
        try {
            $template = $create->handle($company, $request->user(), $request->draft());
        } catch (RecurringTemplateException $exception) {
            throw ValidationException::withMessages([
                'customer_id' => __("recurring_ui.errors.{$exception->reason()}"),
            ]);
        }

        return redirect()->route('recurring.edit', [$company, $template]);
    }

    public function edit(
        Request $request,
        Company $company,
        string $template,
        RecurringTemplateDraftPage $page,
        RecurringUiTranslationBag $translations,
        CustomersUiTranslationBag $customerTranslations,
        CatalogUiTranslationBag $catalogTranslations,
    ): Response {
        return Inertia::render('recurring/edit', [
            ...$page->edit(
                $company,
                $request->user(),
                $template,
                app()->getLocale(),
                $request->session()->pull('inline_customer_id'),
                $request->session()->pull('inline_product_id'),
            ),
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
            'customerTranslations' => $customerTranslations->toArray(),
            'catalogTranslations' => $catalogTranslations->toArray(),
        ]);
    }

    public function update(
        UpdateRecurringTemplateRequest $request,
        Company $company,
        string $template,
        UpdateRecurringTemplateDraft $update,
    ): RedirectResponse {
        try {
            $update->handle($company, $request->user(), $template, $request->draft());
        } catch (RecurringTemplateException|DocumentLineFailure $exception) {
            $field = match ($exception->reason()) {
                'stale' => 'edit_version',
                'customer_defaults_changed' => 'customer_id',
                default => 'lines',
            };

            throw ValidationException::withMessages([
                $field => __("recurring_ui.errors.{$exception->reason()}"),
            ]);
        }

        return back()->with('status', __('recurring_ui.feedback.saved'));
    }
}
