<?php

use App\Modules\Delivery\Contracts\RendersDocumentPdf;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

uses(TestCase::class);

it('proves the selected pure PHP renderer against the outward compatibility fixture', function (): void {
    $lines = [];

    for ($position = 1; $position <= 125; $position++) {
        $lines[] = [
            'position' => $position,
            'description' => "Linia {$position}: ședință, analiză și implementare ".str_repeat('conținut-cu-împachetare ', 4),
            'quantity' => '2 ore · 1 lună',
            'unitPrice' => "100,00\u{00A0}EUR",
            'discount' => $position % 2 === 0 ? '10%' : null,
            'tax' => 'TVA 19%',
            'total' => "214,20\u{00A0}EUR",
        ];
    }

    $document = compatibilityDocument($lines);
    $html = view('pdf.outward-document', [
        'document' => $document,
        'logoDataUri' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        'fontRegular' => base_path('resources/fonts/atkinson-hyperlegible/AtkinsonHyperlegibleNext-Regular.ttf'),
        'fontBold' => base_path('resources/fonts/atkinson-hyperlegible/AtkinsonHyperlegibleNext-Bold.ttf'),
        'fontMono' => base_path('resources/fonts/atkinson-hyperlegible/AtkinsonHyperlegibleMono-Regular.ttf'),
        'fontMonoBold' => base_path('resources/fonts/atkinson-hyperlegible/AtkinsonHyperlegibleMono-Bold.ttf'),
    ])->render();
    $pdf = app(RendersDocumentPdf::class)->render($html);
    $parsed = (new Parser)->parseContent($pdf);
    $text = $parsed->getText();
    $objectContent = implode("\n", array_map(
        static fn ($object): string => $object->getContent(),
        $parsed->getObjects(),
    ));

    expect($pdf)->toStartWith('%PDF-')
        ->and(count($parsed->getPages()))->toBeGreaterThanOrEqual(4)
        ->and($text)->toContain('Ofertă', 'ședință, analiză și implementare', 'Linia 125', 'Termeni cu ă â î ș ț')
        ->and(substr_count($text, 'DESCRIERE'))->toBeGreaterThan(1)
        ->and($pdf)->toContain('AtkinsonHyperlegibleNext', 'AtkinsonHyperlegibleMono', '/Subtype /Image')
        ->and($objectContent)->toContain('0.357 0.227 0.557');
});

/**
 * @param  list<array{position: int, description: string, quantity: string, unitPrice: string, discount: string|null, tax: string|null, total: string}>  $lines
 * @return array<string, mixed>
 */
function compatibilityDocument(array $lines): array
{
    return [
        'kind' => 'Ofertă', 'number' => 'Q-2026-0042', 'status' => 'Ciornă', 'language' => 'ro',
        'issueDate' => '26 aug. 2026', 'validUntil' => '25 sept. 2026',
        'customerReference' => 'PO-ȘȚ-42',
        'theme' => [
            'accentColor' => '#5B3A8E', 'onAccentColor' => '#FFFFFF',
            'textColor' => '#14181C', 'ruleColor' => '#5B3A8E',
        ],
        'company' => [
            'displayName' => 'Compania Știință SRL', 'legalName' => null,
            'address' => ['Strada Întâi 1', 'București', 'RO'],
            'registrations' => ['CUI: RO123456'], 'contacts' => ['office@example.com'],
        ],
        'customer' => [
            'displayName' => 'Clientul Țării SRL', 'contact' => ['Ștefan Țurcan'],
            'address' => ['Cluj-Napoca', 'RO'], 'registrations' => [], 'contacts' => [],
        ],
        'lines' => $lines,
        'subtotal' => "22.500,00\u{00A0}EUR", 'taxTotal' => "4.275,00\u{00A0}EUR",
        'total' => "26.775,00\u{00A0}EUR",
        'bank' => [['label' => 'IBAN / număr cont', 'value' => 'RO49AAAA1B31007593840000']],
        'termsAndConditions' => 'Termeni cu ă â î ș ț '.str_repeat('și împachetare controlată ', 25),
        'notes' => 'Notă vizibilă clientului.', 'logoUrl' => null,
        'labels' => trans('documents_outward.labels', locale: 'ro'),
    ];
}
