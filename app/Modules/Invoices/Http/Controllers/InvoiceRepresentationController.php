<?php

namespace App\Modules\Invoices\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Companies\Data\CompanyAbility;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\CompanyAbilityCheck;
use App\Modules\Delivery\Contracts\RendersDocumentPdf;
use App\Modules\Delivery\Queries\CurrentDocumentLogo;
use App\Modules\Delivery\Queries\CurrentDocumentRepresentation;
use App\Modules\Documents\Data\DocumentKind;
use App\Support\Inertia\InvoicesUiTranslationBag;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InvoiceRepresentationController extends Controller
{
    public function show(
        Request $request,
        Company $company,
        string $invoice,
        CurrentDocumentRepresentation $representation,
        InvoicesUiTranslationBag $translations,
        CompanyAbilityCheck $abilities,
    ): Response {
        $document = $representation->forInvoice($company, $request->user(), $invoice);

        return Inertia::render('invoices/view', [
            'document' => $document->toArray(
                $document->hasLogo ? route('invoices.current.logo', [$company, $invoice], false) : null,
            ),
            'editUrl' => $abilities->allows($request->user(), $company, CompanyAbility::ManageInvoices)
                ? route('invoices.edit', [$company, $invoice], false)
                : null,
            'indexUrl' => route('invoices.index', $company, false),
            'pdfUrl' => route('invoices.current.pdf', [$company, $invoice], false),
            'translations' => $translations->toArray(),
        ]);
    }

    public function pdf(
        Request $request,
        Company $company,
        string $invoice,
        CurrentDocumentRepresentation $representation,
        CurrentDocumentLogo $logo,
        RendersDocumentPdf $renderer,
    ): HttpResponse {
        $document = $representation->forInvoice($company, $request->user(), $invoice);
        $html = view('pdf.outward-document', [
            'document' => $document->toArray(),
            'logoDataUri' => $document->hasLogo
                ? $logo->dataUri($company, $request->user(), $invoice, DocumentKind::Invoice)
                : null,
            'fontRegular' => resource_path('fonts/atkinson-hyperlegible/AtkinsonHyperlegibleNext-Regular.ttf'),
            'fontBold' => resource_path('fonts/atkinson-hyperlegible/AtkinsonHyperlegibleNext-Bold.ttf'),
            'fontMono' => resource_path('fonts/atkinson-hyperlegible/AtkinsonHyperlegibleMono-Regular.ttf'),
            'fontMonoBold' => resource_path('fonts/atkinson-hyperlegible/AtkinsonHyperlegibleMono-Bold.ttf'),
        ])->render();
        $filename = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $document->number), '-');
        $filename = ($filename === '' ? 'invoice' : $filename).'.pdf';

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
        string $invoice,
        CurrentDocumentLogo $logo,
    ): StreamedResponse {
        return $logo->response($company, $request->user(), $invoice, DocumentKind::Invoice);
    }
}
