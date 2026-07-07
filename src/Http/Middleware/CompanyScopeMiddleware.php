<?php

declare(strict_types=1);

namespace Headless\Accounting\Http\Middleware;

use Closure;
use Headless\Accounting\Models\Company;
use Headless\Accounting\Tenancy\CompanyContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CompanyScopeMiddleware — resolves the active Company based on the
 * `X-Company` header (or session value) and binds it to CompanyContext
 * for the lifetime of the request. Apply in the HTTP middleware stack
 * after authentication.
 */
class CompanyScopeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->header('X-Company') ?? $request->session()->get('company_code');
        if ($code) {
            $company = Company::query()->where('code', $code)->first();
            if ($company) {
                CompanyContext::set($company);
            }
        }

        $response = $next($request);

        CompanyContext::forget();

        return $response;
    }
}
