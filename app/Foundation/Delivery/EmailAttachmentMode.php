<?php

namespace App\Foundation\Delivery;

enum EmailAttachmentMode: string
{
    case SecureLinkOnly = 'SECURE_LINK_ONLY';
    case AttachPdf = 'ATTACH_PDF';
}
