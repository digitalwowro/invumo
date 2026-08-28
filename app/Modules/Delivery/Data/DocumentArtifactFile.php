<?php

namespace App\Modules\Delivery\Data;

final readonly class DocumentArtifactFile
{
    public function __construct(public string $disk, public string $key) {}
}
