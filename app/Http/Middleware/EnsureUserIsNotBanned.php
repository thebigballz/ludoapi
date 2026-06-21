<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsNotBanned
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->is_banned) {
            return response()->json(['message' => 'Your account has been suspended.'], 403);
        }

        return $next($request);
    }
}