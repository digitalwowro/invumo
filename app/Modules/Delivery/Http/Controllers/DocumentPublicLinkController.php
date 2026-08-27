<?php

namespace App\Modules\Delivery\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Actions\CreatePublicDocumentLink;
use App\Modules\Delivery\Actions\RegeneratePublicDocumentLink;
use App\Modules\Delivery\Actions\RevokePublicDocumentLink;
use App\Modules\Delivery\Exceptions\PublicDocumentLinkException;
use App\Modules\Documents\Data\DocumentKind;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class DocumentPublicLinkController extends Controller
{
    public function store(
        Request $request,
        Company $company,
        string $document,
        CreatePublicDocumentLink $create,
    ): RedirectResponse {
        $create->handle($company, $request->user(), $document, $this->kind($request));

        return back()->with('status', __('public_documents.feedback.created'));
    }

    public function regenerate(
        Request $request,
        Company $company,
        string $document,
        RegeneratePublicDocumentLink $regenerate,
    ): RedirectResponse {
        try {
            $regenerate->handle($company, $request->user(), $document, $this->kind($request));
        } catch (PublicDocumentLinkException $exception) {
            throw ValidationException::withMessages([
                'public_link' => __("public_documents.errors.{$exception->reason()}"),
            ]);
        }

        return back()->with('status', __('public_documents.feedback.regenerated'));
    }

    public function destroy(
        Request $request,
        Company $company,
        string $document,
        RevokePublicDocumentLink $revoke,
    ): RedirectResponse {
        $revoke->handle($company, $request->user(), $document, $this->kind($request));

        return back()->with('status', __('public_documents.feedback.revoked'));
    }

    private function kind(Request $request): DocumentKind
    {
        return DocumentKind::from((string) $request->route('document_kind'));
    }
}
