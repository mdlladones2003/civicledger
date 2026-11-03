<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware(RequirePermission::class.':events.manage') (any one of several permissions passes). */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        abort_unless(
            $user && collect($permissions)->contains(fn (string $p) => $user->hasPermission($p)),
            403,
            'You do not have permission to do this.',
        );

        return $next($request);
    }
}
