<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogMeetingUploadAttempt
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('post') && $request->is('api/v1/meetings')) {
            Log::info('Intento de carga recibido por el endpoint.', [
                'ip' => $request->ip(),
                'content_type' => $request->header('Content-Type'),
                'content_length' => $request->header('Content-Length'),
                'authorization_present' => filled($request->bearerToken()),
                'idempotency_present' => filled($request->header('Idempotency-Key')),
            ]);
        }

        return $next($request);
    }
}
