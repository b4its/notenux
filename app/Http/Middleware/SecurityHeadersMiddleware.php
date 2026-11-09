<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request and apply strict security hardening headers.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Prevent information disclosure regarding server tech
        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
            header_remove('Server');
        }
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        // Control DNS prefetching to avoid privacy leakage
        $response->headers->set('X-DNS-Prefetch-Control', 'off');

        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Cross-site scripting filter block
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Prevent Adobe Flash / PDF cross-domain policy file abuse
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // Prevent referrer leakage across domains
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Cross-Origin isolation headers (Spectre, timing attacks, window.opener protection)
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        // Restrict sensitive browser APIs
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), accelerometer=(), gyroscope=(), magnetometer=(), usb=()');

        // Content Security Policy
        $csp = "default-src 'self'; "
            ."script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
            ."style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
            ."font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com data:; "
            ."img-src 'self' data: blob: https:; "
            ."connect-src 'self'; "
            ."frame-ancestors 'self'; "
            ."form-action 'self'; "
            ."base-uri 'self'; "
            ."object-src 'none'; "
            .'upgrade-insecure-requests;';
        $response->headers->set('Content-Security-Policy', $csp);

        // Strict-Transport-Security when HTTPS is used
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Cache control: High-performance caching for immutable Vite assets and static images, prevent caching of sensitive state
        if ($request->is('build/*')) {
            $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');
        } elseif ($request->is('assets/*')) {
            $response->headers->set('Cache-Control', 'public, max-age=2592000, no-transform');
        } elseif ($request->is('studio*', 'profile*', 'api/*', 'categories*', 'folders*', 'login*', 'register*', 'panel*', 'logout*')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            if ($request->is('studio*', 'profile*', 'api/*', 'categories*', 'folders*', 'panel*')) {
                $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
            }
        }

        return $response;
    }
}
