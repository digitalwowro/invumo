<?php

namespace App\Modules\Delivery\Support;

use Illuminate\Http\Request;

final readonly class PublicDocumentRateLimitKey
{
    public static function source(Request $request): string
    {
        $ip = $request->ip() ?? 'unknown';
        $packed = @inet_pton($ip);
        $normalized = $packed === false ? 'unknown' : bin2hex($packed);

        return hash_hmac('sha256', $normalized, (string) config('app.key'));
    }

    public static function token(Request $request): string
    {
        return hash('sha256', PublicDocumentRequestToken::plainText($request));
    }
}
