<?php

namespace App\Foundation\Http;

use App\Support\Inertia\ErrorPageTranslationBag;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class SafeErrorResponse
{
    /** @var list<int> */
    private const STATUSES = [403, 404, 500, 503];

    public function render(Response $response, Request $request): Response
    {
        $status = $response->getStatusCode();

        if (! in_array($status, self::STATUSES, true)) {
            return $response;
        }

        $translations = app(ErrorPageTranslationBag::class)->for($status);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $status,
                'message' => $translations['page']['title'],
            ], $status);
        }

        return Inertia::render('errors/show', [
            'status' => $status,
            'translations' => $translations,
        ])->toResponse($request)->setStatusCode($status);
    }
}
