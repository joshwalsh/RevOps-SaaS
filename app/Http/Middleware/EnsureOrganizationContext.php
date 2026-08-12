<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationContext
{
    /**
     * Handle an incoming request.
     *
     * Resolves UX context only (which organization is "active"), not
     * authorization — that stays in policies.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->current_organization_id !== null
            && ! $user->organizations()->whereKey($user->current_organization_id)->exists()) {
            $user->forceFill(['current_organization_id' => $user->organizations()->first()?->id])->save();
        }

        if ($user->current_organization_id === null && ! $user->isSuperAdmin()) {
            return redirect()->route('organizations.create');
        }

        return $next($request);
    }
}
