<?php

namespace App\Modules\Delivery\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Delivery\Http\Requests\PublicQuoteDecisionRequest;
use App\Modules\Delivery\Support\PublicDocumentRequestToken;
use App\Modules\Quotes\Actions\DecidePublicQuote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class PublicQuoteDecisionController extends Controller
{
    public function __invoke(
        PublicQuoteDecisionRequest $request,
        DecidePublicQuote $decide,
    ): RedirectResponse {
        $token = PublicDocumentRequestToken::plainText($request);

        $result = $decide->handle($token, $request->decision());
        abort_if($result === null, 404);

        if ($result->failure !== null) {
            throw ValidationException::withMessages([
                'decision' => __("public_documents.errors.{$result->failure}"),
            ]);
        }

        $location = route('public-quotes.show', ['token' => $token], false);

        return redirect()->to($location, 303);
    }
}
