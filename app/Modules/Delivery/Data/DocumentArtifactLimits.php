<?php

namespace App\Modules\Delivery\Data;

final class DocumentArtifactLimits
{
    // Leaves room for base64 expansion, headers, recipients, and message content under ZeptoMail's 15 MB total.
    public const MAX_BYTES = 11 * 1024 * 1024;
}
