<?php

namespace App\Modules\Quotes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Models\Company;
use App\Modules\Delivery\Contracts\RendersDocumentPdf;
use App\Modules\Delivery\Queries\CurrentQuoteLogo;
use App\Modules\Delivery\Queries\CurrentQuoteRepresentation;
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
        CurrentQuoteRepresentation $representation,
        QuotesUiTranslationBag $translations,
    ): Response {
        $document = $representation->for($company, $request->user(), $quote);

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
        CurrentQuoteRepresentation $representation,
        CurrentQuoteLogo $logo,
        RendersDocumentPdf $renderer,
    ): HttpResponse {
        $document = $representation->for($company, $request->user(), $quote);
        $html = view('pdf.outward-document', [
            'document' => $document->toArray(),
            'logoDataUri' => $document->hasLogo ? $logo->dataUri($company, $request->user(), $quote) : null,
            'fontRegular' => base_path('resources/fonts/atkinson-hyperlegible/AtkinsonHyperlegibleNext-Regular.ttf'),
            'fontBold' => base_path('resources/fonts/atkinson-hyperlegible/AtkinsonHyperlegibleNext-Bold.ttf'),
            'fontMono' => base_path('resources/fonts/atkinson-hyperlegible/AtkinsonHyperlegibleMono-Regular.ttf'),
            'fontMonoBold' => base_path('resources/fonts/atkinson-hyperlegible/AtkinsonHyperlegibleMono-Bold.ttf'),
        ])->render();
        $filename = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $document->number), '-');
        $filename = ($filename === '' ? 'quote' : $filename).'.pdf';

        return response($renderer->render($html), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function logo(
        Request $request,
        Company $company,
        string $quote,
        CurrentQuoteLogo $logo,
    ): StreamedResponse {
        return $logo->response($company, $request->user(), $quote);
    }
}
