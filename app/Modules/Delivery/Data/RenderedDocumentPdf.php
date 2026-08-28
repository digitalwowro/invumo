<?php

namespace App\Modules\Delivery\Data;

final readonly class RenderedDocumentPdf
{
    public function __construct(public string $bytes, public string $fileName) {}
}
