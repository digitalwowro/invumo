<?php

namespace App\Modules\Delivery\Support;

final class DocumentEmailHtml
{
    public function render(
        string $body,
        string $buttonLabel,
        string $buttonUrl,
        ?string $signature,
        string $language,
        string $accentColor,
        string $onAccentColor,
    ): string {
        $bodyHtml = nl2br(e($body));
        $signatureHtml = $signature === null ? '' : '<p style="margin:24px 0 0">'.nl2br(e($signature)).'</p>';

        return '<!doctype html><html lang="'.e($language).'"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            .'<body style="margin:0;background:#f4f5f6">'
            .'<div style="max-width:640px;margin:0 auto;padding:32px 20px;font-family:Arial,sans-serif;color:#14181C">'
            .'<div style="background:#fff;border:1px solid #dfe3e8;border-radius:12px;padding:32px">'
            .'<p style="margin:0 0 24px;line-height:1.6">'.$bodyHtml.'</p>'
            .'<p style="margin:0"><a href="'.e($buttonUrl).'" style="display:inline-block;padding:12px 18px;border-radius:8px;text-decoration:none;background:'
            .e($accentColor).';color:'.e($onAccentColor).';font-weight:700">'.e($buttonLabel).'</a></p>'
            .$signatureHtml.'</div></div></body></html>';
    }
}
