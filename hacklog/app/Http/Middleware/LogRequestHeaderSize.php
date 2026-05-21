<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogRequestHeaderSize
{
    public function handle(Request $request, Closure $next): Response
    {
        // Off by default; enable in env with LOG_HEADER_SIZES=true.
        if (filter_var(env('LOG_HEADER_SIZES', false), FILTER_VALIDATE_BOOL)) {
            $totalBytes = 0;

            foreach ($request->headers->all() as $name => $values) {
                foreach ($values as $value) {
                    // Approximate raw header line size: "Name: Value\r\n".
                    $totalBytes += strlen($name) + 2 + strlen((string) $value) + 2;
                }
            }

            $cookieHeader = (string) $request->headers->get('cookie', '');
            $cookieBytes = strlen($cookieHeader);

            // Log all requests while enabled so growth patterns are visible.
            logger()->info('Request header size', [
                'path' => $request->path(),
                'method' => $request->method(),
                'host' => $request->getHost(),
                'total_header_bytes' => $totalBytes,
                'cookie_header_bytes' => $cookieBytes,
                'cookie_pairs_count' => $cookieHeader === '' ? 0 : substr_count($cookieHeader, ';') + 1,
                'user_agent_bytes' => strlen((string) $request->userAgent()),
                'referer' => (string) $request->headers->get('referer', ''),
            ]);
        }

        return $next($request);
    }
}
