<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user && $user->isActive(), 403, 'This account is not active.');
        abort_unless(in_array($user->role->value, $roles, true), 403, 'You do not have access to this area.');

        return $next($request);
    }
}
