<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SiidEmbedSecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $origins = collect(config('siid.looker.allowed_hosts', []))
            ->flatMap(fn (string $host): array => ["https://{$host}", "https://*.{$host}"])
            ->push(rtrim((string) config('superset.url'), '/'))
            ->filter()
            ->unique()
            ->implode(' ');
        $response->headers->set('Content-Security-Policy', "frame-src 'self' {$origins}; frame-ancestors 'self';");
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
