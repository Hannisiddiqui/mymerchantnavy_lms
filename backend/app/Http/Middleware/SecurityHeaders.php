<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Browser-side protections added to every response of the API and the admin panel. */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $isApi = $request->is('api/*');

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'X-Frame-Options' => 'DENY',
            // The API only returns JSON: nothing in it may ever load or run anything. The admin panel needs its own scripts,
            // so there it only blocks framing, plugins, base-tag tricks and form posts to other sites.
            'Content-Security-Policy' => $isApi
                ? "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'"
                : "frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'",
        ];
        // Login, 2FA, account and admin pages must never be kept by a browser or proxy.
        if (! $isApi || $request->is('api/v1/auth/*', 'api/v1/me*')) {
            $headers['Cache-Control'] = 'no-store, private';
        }
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name) || $name === 'Cache-Control') {
                $response->headers->set($name, $value);
            }
        }
        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
