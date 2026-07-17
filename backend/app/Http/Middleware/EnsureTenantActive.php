<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user=$request->user();
        abort_if($user?->tenant_id && $user->tenant?->status !== 'ACTIVE', 403, 'This tenant is suspended.');

        return $next($request);
    }
}
