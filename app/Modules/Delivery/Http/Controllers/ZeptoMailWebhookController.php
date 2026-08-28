<?php

namespace App\Modules\Delivery\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Delivery\Contracts\ParsesProviderWebhook;
use App\Modules\Delivery\Contracts\ProviderWebhookRequestException;
use App\Modules\Delivery\Queries\ResolveProviderDeliveryAttempt;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ZeptoMailWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        ParsesProviderWebhook $webhook,
        ResolveProviderDeliveryAttempt $resolve,
    ): JsonResponse {
        $rawBody = $request->getContent();
        $contentType = strtolower(trim(explode(';', (string) $request->header('content-type'))[0]));

        if ($rawBody !== '' && $contentType !== 'application/x-www-form-urlencoded') {
            return response()->json(['accepted' => false], 400);
        }

        try {
            $event = $webhook->parse(
                $rawBody,
                $request->header(ParsesProviderWebhook::AUTHENTICATION_HEADER),
                CarbonImmutable::now('UTC'),
            );
        } catch (ProviderWebhookRequestException $exception) {
            return response()->json(
                ['accepted' => false],
                $exception->getMessage() === 'unauthorized' ? 401 : 400,
            );
        }

        if ($event !== null) {
            $resolve->handle($event);
        }

        return response()->json(['accepted' => true], $rawBody === '' ? 200 : 202);
    }
}
