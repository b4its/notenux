<?php

use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustHosts(at: function () {
            $hosts = ['localhost', '127.0.0.1'];
            if ($appHost = parse_url((string) config('app.url'), PHP_URL_HOST)) {
                $hosts[] = $appHost;
            }
            $configured = env('TRUSTED_HOSTS');
            if ($configured) {
                $hosts = array_merge($hosts, array_map('trim', explode(',', (string) $configured)));
            }

            return array_values(array_unique($hosts));
        }, subdomains: true);

        $trustedProxies = env('TRUSTED_PROXIES');
        $middleware->trustProxies(
            at: ($trustedProxies !== null && $trustedProxies !== '') ? array_map('trim', explode(',', $trustedProxies)) : '*',
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO
        );
        $middleware->append(SecurityHeadersMiddleware::class);
        $middleware->redirectTo(
            guests: '/login',
            users: '/studio',
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
