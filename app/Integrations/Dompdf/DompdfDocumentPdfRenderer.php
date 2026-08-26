<?php

namespace App\Integrations\Dompdf;

use App\Modules\Delivery\Contracts\RendersDocumentPdf;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Filesystem\Filesystem;

final readonly class DompdfDocumentPdfRenderer implements RendersDocumentPdf
{
    public function __construct(private Filesystem $files) {}

    public function render(string $html): string
    {
        $cache = storage_path('framework/cache/dompdf');
        $fonts = resource_path('fonts/atkinson-hyperlegible');
        $this->files->ensureDirectoryExists($cache, 0700, true);
        $options = new Options;
        $options->setChroot([$fonts, $cache]);
        $options->setFontDir($cache);
        $options->setFontCache($cache);
        $options->setTempDir($cache);
        $options->setDefaultFont('Atkinson Hyperlegible Next');
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setIsFontSubsettingEnabled(true);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }
}
