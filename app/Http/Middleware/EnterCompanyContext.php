<?php

namespace App\Http\Middleware;

use App\Foundation\Tenancy\TenantContext;
use App\Modules\Companies\Models\Company;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnterCompanyContext
{
    public function __construct(private TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->route('company');

        if (! $company instanceof Company || $request->user() === null) {
            abort(404);
        }

        try {
            return $this->tenantContext->runForMember(
                $request->user(),
                $company->id,
                function () use ($company, $next, $request): Response {
                    $response = $next($request);

                    if ($response->getStatusCode() < 400) {
                        if ($request->session()->pull('company_context.skip_remember_once', false)) {
                            $request->session()->forget('last_company_id');
                        } else {
                            $request->session()->put('last_company_id', $company->id);
                        }
                    }

                    return $response;
                },
            );
        } catch (AuthorizationException) {
            abort(404);
        }
    }
}
