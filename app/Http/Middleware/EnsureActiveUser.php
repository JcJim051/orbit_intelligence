<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->active) {
            $request->user()?->tokens()->delete();
            if ($request->hasSession()) {
                Auth::logout();
            }
            abort(403, 'La cuenta está inactiva.');
        }

        return $next($request);
    }
}
