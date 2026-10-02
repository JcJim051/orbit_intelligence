<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetManagementLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale('es');

        return $next($request);
    }
}
