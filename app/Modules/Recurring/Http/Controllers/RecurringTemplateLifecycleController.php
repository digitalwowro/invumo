<?php

namespace App\Modules\Recurring\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Recurring\Actions\DuplicateCompletedRecurringTemplate;
use App\Modules\Recurring\Actions\TransitionRecurringTemplate;
use App\Modules\Recurring\Actions\UpdateRecurringTemplateSchedule;
use App\Modules\Recurring\Data\RecurringTemplateTransition;
use App\Modules\Recurring\Exceptions\RecurringTemplateException;
use App\Modules\Recurring\Http\Requests\DuplicateRecurringTemplateRequest;
use App\Modules\Recurring\Http\Requests\RecurringTransitionRequest;
use App\Modules\Recurring\Http\Requests\UpdateRecurringScheduleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class RecurringTemplateLifecycleController extends Controller
{
    public function schedule(
        UpdateRecurringScheduleRequest $request,
        Company $company,
        string $template,
        UpdateRecurringTemplateSchedule $update,
    ): RedirectResponse {
        try {
            $update->handle($company, $request->user(), $template, $request->schedule());
        } catch (RecurringTemplateException $exception) {
            $this->validationError($exception, 'schedule');
        }

        return back()->with('status', __('recurring_ui.feedback.schedule_saved'));
    }

    public function transition(
        RecurringTransitionRequest $request,
        Company $company,
        string $template,
        string $transition,
        TransitionRecurringTemplate $change,
    ): RedirectResponse {
        $transition = RecurringTemplateTransition::from(strtoupper($transition));
        try {
            $change->handle(
                $company, $request->user(), $template, $transition,
                $request->editVersion(), $request->boolean('confirmed'),
            );
        } catch (RecurringTemplateException $exception) {
            $this->validationError($exception, 'transition');
        }

        return back()->with('status', __("recurring_ui.feedback.{$transition->value}"));
    }

    public function duplicate(
        DuplicateRecurringTemplateRequest $request,
        Company $company,
        string $template,
        DuplicateCompletedRecurringTemplate $duplicate,
    ): RedirectResponse {
        try {
            $copy = $duplicate->handle(
                $company, $request->user(), $template,
                (string) $request->validated('creation_key'),
            );
        } catch (RecurringTemplateException $exception) {
            $this->validationError($exception, 'template');
        }

        return redirect()->route('recurring.edit', [$company, $copy]);
    }

    private function validationError(RecurringTemplateException $exception, string $field): never
    {
        throw ValidationException::withMessages([
            $exception->reason() === 'stale' ? 'edit_version' : $field => __("recurring_ui.errors.{$exception->reason()}"),
        ]);
    }
}
