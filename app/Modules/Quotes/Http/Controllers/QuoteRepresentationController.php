<?php

namespace App\Modules\Quotes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Queries\CurrentDocumentLogo;
use App\Modules\Delivery\Queries\CurrentDocumentRepresentation;
use App\Modules\Delivery\Queries\DocumentPdfContent;
use App\Modules\Documents\Data\DocumentKind;
use App\Support\Inertia\QuotesUiTranslationBag;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class QuoteRepresentationController extends Controller
{
    public function show(
        Request $request,
        Company $company,
        string $quote,
        CurrentDocumentRepresentation $representation,
        QuotesUiTranslationBag $translations,
    ): Response {
        $document = $representation->forQuote($company, $request->user(), $quote);

        return Inertia::render('quotes/view', [
            'document' => $document->toArray(
                $document->hasLogo ? route('quotes.current.logo', [$company, $quote], false) : null,
            ),
            'editUrl' => route('quotes.edit', [$company, $quote], false),
            'indexUrl' => route('quotes.index', $company, false),
            'pdfUrl' => route('quotes.current.pdf', [$company, $quote], false),
            'translations' => $translations->toArray(),
        ]);
    }

    public function pdf(
        Request $request,
        Company $company,
        string $quote,
        CurrentDocumentRepresentation $representation,
        DocumentPdfContent $pdf,
    ): HttpResponse {
        $document = $representation->forQuote($company, $request->user(), $quote);
        $rendered = $pdf->render($quote, $document);

        return response($rendered->bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$rendered->fileName.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function logo(
        Request $request,
        Company $company,
        string $quote,
        CurrentDocumentLogo $logo,
    ): StreamedResponse {
        return $logo->response($company, $request->user(), $quote, DocumentKind::Quote);
    }
}
