<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Use config() so tests can switch env
        $env   = (string) config('app.env');      // 'local','production','testing',...
        $debug = (bool)   config('app.debug');

        // Consider reverse-proxy header explicitly (works even if TrustProxies isn't set)
        $proto   = strtolower((string) $request->headers->get('x-forwarded-proto', ''));
        $isHttps = $request->isSecure() || $proto === 'https';

        // Safe, classic headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', "geolocation=(), microphone=(), camera=(), payment=(), usb=()");
        $response->headers->set('X-XSS-Protection', '0');

        // CSP (light)
        $csp = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "manifest-src 'self'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https:",
            "style-src 'self' 'unsafe-inline' https:",
            "script-src 'self' 'unsafe-inline' https:",
            "connect-src 'self' https:",
        ];

        $cspHeader = ($env === 'local' || $debug)
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        // Dev allowances for HMR etc.
        if ($cspHeader === 'Content-Security-Policy-Report-Only') {
            foreach ($csp as &$d) {
                if (strpos($d, 'connect-src') === 0) $d .= ' http: ws: wss:';
            }
            unset($d);
        }

        $response->headers->set($cspHeader, implode('; ', $csp));

        // HSTS only in prod + https
        if ($env === 'production' && $isHttps) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=15552000; includeSubDomains; preload'
            );
        }

        return $response;
    }
}
