<?php

namespace App\Modules\Delivery\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Delivery\Contracts\RendersDocumentPdf;
use App\Modules\Delivery\Data\OutwardDocument;
use App\Modules\Delivery\Data\ResolvedPublicDocument;
use App\Modules\Delivery\Http\Middleware\RedactPublicDocumentToken;
use App\Modules\Delivery\Queries\CurrentDocumentRepresentation;
use App\Modules\Delivery\Queries\DocumentLogoContent;
use App\Modules\Delivery\Queries\ResolvePublicDocument;
use App\Modules\Documents\Data\DocumentKind;
use App\Support\Inertia\PublicDocumentsTranslationBag;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

final class PublicDocumentController extends Controller
{
    public function quote(Request $request): Response
    {
        return $this->show($request, DocumentKind::Quote);
    }

    public function invoice(Request $request): Response
    {
        return $this->show($request, DocumentKind::Invoice);
    }

    public function quotePdf(Request $request, RendersDocumentPdf $renderer): HttpResponse
    {
        return $this->pdf($request, DocumentKind::Quote, $renderer);
    }

    public function invoicePdf(Request $request, RendersDocumentPdf $renderer): HttpResponse
    {
        return $this->pdf($request, DocumentKind::Invoice, $renderer);
    }

    private function show(Request $request, DocumentKind $kind): Response
    {
        $resolved = $this->resolve($request, $kind);
        $document = $resolved['document'];
        app()->setLocale($document->language);

        return Inertia::render('public/document', [
            'document' => $document->toArray($resolved['logoDataUri']),
            'pdfUrl' => route(
                $kind === DocumentKind::Quote ? 'public-quotes.pdf' : 'public-invoices.pdf',
                ['token' => $resolved['token']],
                false,
            ),
            'translations' => app(PublicDocumentsTranslationBag::class)->toArray(
                $document->language,
            ),
        ]);
    }

    private function pdf(
        Request $request,
        DocumentKind $kind,
        RendersDocumentPdf $renderer,
    ): HttpResponse {
        $resolved = $this->resolve($request, $kind);
        $document = $resolved['document'];
        $html = view('pdf.outward-document', [
            'document' => $document->toArray(),
            'logoDataUri' => $resolved['logoDataUri'],
            'fontRegular' => resource_path('fonts/atkinson-hyperlegible/AtkinsonHyperlegibleNext-Regular.ttf'),
            'fontBold' => resource_path('fonts/atkinson-hyperlegible/AtkinsonHyperlegibleNext-Bold.ttf'),
            'fontMono' => resource_path('fonts/atkinson-hyperlegible/AtkinsonHyperlegibleMono-Regular.ttf'),
            'fontMonoBold' => resource_path('fonts/atkinson-hyperlegible/AtkinsonHyperlegibleMono-Bold.ttf'),
        ])->render();
        $fallback = $kind === DocumentKind::Quote ? 'quote' : 'invoice';
        $filename = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $document->number), '-');

        return response($renderer->render($html), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.($filename ?: $fallback).'.pdf"',
        ]);
    }

    /** @return array{document: OutwardDocument, logoDataUri: string|null, token: string} */
    private function resolve(Request $request, DocumentKind $kind): array
    {
        $token = RedactPublicDocumentToken::plainText($request);
        $result = app(ResolvePublicDocument::class)->run(
            $token,
            $kind,
            function (ResolvedPublicDocument $resolved) use ($kind): array {
                $representation = app(CurrentDocumentRepresentation::class);
                $document = $kind === DocumentKind::Quote
                    ? $representation->publicQuote($resolved->company, $resolved->document)
                    : $representation->publicInvoice($resolved->company, $resolved->document);

                return [
                    'document' => $document,
                    'logoDataUri' => $document->hasLogo
                        ? app(DocumentLogoContent::class)->dataUri($resolved->document->id)
                        : null,
                ];
            },
        );
        abort_unless(is_array($result), 404);

        return [...$result, 'token' => $token];
    }
}
