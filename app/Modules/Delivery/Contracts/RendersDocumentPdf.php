<?php

namespace App\Modules\Delivery\Contracts;

interface RendersDocumentPdf
{
    public function render(string $html): string;
}
