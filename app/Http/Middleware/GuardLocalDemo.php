<?php

namespace App\Http\Middleware;

use App\Support\DemoMode;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;

class GuardLocalDemo
{
    public function handle(Request $request, Closure $next)
    {
        try {
            DemoMode::assertSafe();
        } catch (RuntimeException) {
            abort(503, 'Unsafe demo configuration. Use the documented isolated local setup.');
        }

        if (config('pickleplay.demo')) {
            abort_unless(in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'], true)
                && in_array($request->ip(), ['127.0.0.1', '::1'], true), 403);
        }

        return $next($request);
    }
}
