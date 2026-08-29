<?php

namespace App\Modules\Recurring\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Recurring\Actions\DeleteRecurringTemplateDraft;
use App\Modules\Recurring\Exceptions\RecurringTemplateException;
use App\Modules\Recurring\Http\Requests\DeleteRecurringTemplateRequest;
use App\Modules\Recurring\Http\Requests\RecurringTemplateListRequest;
use App\Modules\Recurring\Queries\RecurringTemplateListPage;
use App\Support\Inertia\RecurringUiTranslationBag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class RecurringTemplateController extends Controller
{
    public function index(
        RecurringTemplateListRequest $request,
        Company $company,
        RecurringTemplateListPage $page,
        RecurringUiTranslationBag $translations,
    ): Response {
        return Inertia::render('recurring/index', [
            ...$page->for($company, $request->user(), $request),
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
        ]);
    }

    public function destroy(
        DeleteRecurringTemplateRequest $request,
        Company $company,
        string $template,
        DeleteRecurringTemplateDraft $delete,
    ): RedirectResponse {
        try {
            $delete->handle($company, $request->user(), $template);
        } catch (RecurringTemplateException $exception) {
            throw ValidationException::withMessages([
                'template' => __("recurring_ui.errors.{$exception->reason()}"),
            ]);
        }

        return redirect()->route('recurring.index', $company)
            ->with('status', __('recurring_ui.feedback.deleted'));
    }
}
