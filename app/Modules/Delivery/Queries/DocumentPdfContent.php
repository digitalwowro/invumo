<?php

namespace App\Modules\Delivery\Queries;

use App\Modules\Delivery\Contracts\RendersDocumentPdf;
use App\Modules\Delivery\Data\OutwardDocument;
use App\Modules\Delivery\Data\RenderedDocumentHtml;
use App\Modules\Delivery\Data\RenderedDocumentPdf;

final readonly class DocumentPdfContent
{
    public function __construct(
        private DocumentLogoContent $logo,
        private RendersDocumentPdf $renderer,
    ) {}

    public function render(string $documentId, OutwardDocument $document): RenderedDocumentPdf
    {
        $prepared = $this->prepare($documentId, $document);

        return new RenderedDocumentPdf(
            $this->renderer->render($prepared->html),
            $prepared->fileName,
        );
    }

    public function prepare(string $documentId, OutwardDocument $document): RenderedDocumentHtml
    {
        $html = view('pdf.outward-document', [
            'document' => $document->toArray(),
            'logoDataUri' => $document->hasLogo ? $this->logo->dataUri($documentId) : null,
            'fontRegular' => resource_path('fonts/atkinson-hyperlegible/AtkinsonHyperlegibleNext-Regular.ttf'),
            'fontBold' => resource_path('fonts/atkinson-hyperlegible/AtkinsonHyperlegibleNext-Bold.ttf'),
            'fontMono' => resource_path('fonts/atkinson-hyperlegible/AtkinsonHyperlegibleMono-Regular.ttf'),
            'fontMonoBold' => resource_path('fonts/atkinson-hyperlegible/AtkinsonHyperlegibleMono-Bold.ttf'),
        ])->render();
        $baseName = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $document->number), '-');

        return new RenderedDocumentHtml(
            $html,
            ($baseName === '' ? strtolower($document->kind) : $baseName).'.pdf',
        );
    }
}
