<?php

namespace App\Modules\Delivery\Http\Controllers;

use App\Foundation\Localization\SupportedLocales;
use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanySettingsNavigation;
use App\Modules\Delivery\Actions\ResetCompanyEmailTemplate;
use App\Modules\Delivery\Actions\SaveCompanyEmailTemplate;
use App\Modules\Delivery\Data\EmailTemplateEvent;
use App\Modules\Delivery\Exceptions\EmailTemplateException;
use App\Modules\Delivery\Http\Requests\SaveCompanyEmailTemplateRequest;
use App\Modules\Delivery\Queries\CompanyEmailTemplatesPage;
use App\Modules\Delivery\Queries\PreviewCompanyEmailTemplate;
use App\Support\Inertia\CompaniesUiTranslationBag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CompanyEmailTemplateController extends Controller
{
    public function index(
        Request $request,
        Company $company,
        CompanyEmailTemplatesPage $page,
        CompanySettingsNavigation $navigation,
        CompaniesUiTranslationBag $translations,
    ): Response {
        return Inertia::render('companies/settings/email-templates', [
            'company' => ['id' => $company->id, 'name' => $company->name],
            ...$page->for($company, $request->user()),
            'companySettingsNavigation' => $navigation->for($company, $request->user())['items'],
            'status' => $request->session()->get('status'),
            'translations' => $translations->toArray(),
        ]);
    }

    public function update(
        SaveCompanyEmailTemplateRequest $request,
        Company $company,
        SaveCompanyEmailTemplate $save,
    ): RedirectResponse {
        try {
            $save->handle($company, $request->user(), $request->template());
        } catch (EmailTemplateException $exception) {
            $this->invalidTemplate($exception);
        }

        return back()->with('status', __('companies_ui.settings.email_templates.feedback.saved'));
    }

    public function preview(
        SaveCompanyEmailTemplateRequest $request,
        Company $company,
        PreviewCompanyEmailTemplate $preview,
    ): JsonResponse {
        return response()->json(
            $preview->for($company, $request->user(), $request->template())->toArray(),
        );
    }

    public function destroy(
        Request $request,
        Company $company,
        string $event,
        string $language,
        ResetCompanyEmailTemplate $reset,
    ): RedirectResponse {
        $eventType = EmailTemplateEvent::tryFrom($event);
        abort_unless($eventType instanceof EmailTemplateEvent, 404);
        abort_unless(SupportedLocales::includes($language), 404);
        $reset->handle($company, $request->user(), $eventType, $language);

        return back()->with('status', __('companies_ui.settings.email_templates.feedback.reset'));
    }

    private function invalidTemplate(EmailTemplateException $exception): never
    {
        throw ValidationException::withMessages([
            $exception->field() => __('companies_ui.settings.email_templates.errors.invalid_template'),
        ]);
    }
}
