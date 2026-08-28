<?php

namespace App\Modules\Delivery\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Actions\RetryDocumentDelivery;
use App\Modules\Delivery\Actions\SendDocument;
use App\Modules\Delivery\Exceptions\DocumentDeliveryException;
use App\Modules\Delivery\Http\Requests\RetryDocumentDeliveryRequest;
use App\Modules\Delivery\Http\Requests\SendDocumentRequest;
use App\Modules\Documents\Data\DocumentKind;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class DocumentDeliveryController extends Controller
{
    public function store(
        SendDocumentRequest $request,
        Company $company,
        string $document,
        SendDocument $send,
    ): RedirectResponse {
        try {
            $send->handle(
                $company,
                $request->user(),
                $document,
                DocumentKind::from((string) $request->route('document_kind')),
                $request->delivery(),
            );
        } catch (DocumentDeliveryException $exception) {
            throw ValidationException::withMessages([
                $exception->validationField()
                    ?? ($exception->reason() === 'stale' ? 'edit_version' : 'delivery') => __("document_delivery.errors.{$exception->reason()}"),
            ]);
        }

        return back()->with('status', __('document_delivery.feedback.queued'));
    }

    public function retry(
        RetryDocumentDeliveryRequest $request,
        Company $company,
        string $document,
        string $delivery,
        RetryDocumentDelivery $retry,
    ): RedirectResponse {
        try {
            $retry->handle($company, $request->user(), $document, $delivery, true);
        } catch (DocumentDeliveryException $exception) {
            throw ValidationException::withMessages([
                'delivery' => __("document_delivery.errors.{$exception->reason()}"),
            ]);
        }

        return back()->with('status', __('document_delivery.feedback.retry_queued'));
    }
}
