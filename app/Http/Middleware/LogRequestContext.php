<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class LogRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) ($request->header('X-Request-Id') ?: Str::uuid());
        $request->headers->set('X-Request-Id', $requestId);
        $startedAt = microtime(true);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        Log::channel((string) config('logging.default', 'stack'))->info('api.request', [
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => $request->path(),
            'route' => $request->route()?->getName(),
            'status' => $response->getStatusCode(),
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            'user_id' => $request->user()?->getAuthIdentifier(),
        ]);

        return $response;
    }
}
