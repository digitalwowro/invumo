<?php

namespace App\Modules\Delivery\Data;

final readonly class RenderedDocumentHtml
{
    public function __construct(public string $html, public string $fileName) {}
}
