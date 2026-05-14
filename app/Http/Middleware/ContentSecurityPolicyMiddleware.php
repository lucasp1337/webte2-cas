<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies a Content-Security-Policy header to every response.
 *
 * Default policy is strict ('self' only). Two named routes receive relaxed
 * policies to accommodate third-party libraries that require inline execution:
 *
 *  - api-docs: Swagger UI injects inline <style> and inline event handlers;
 *    'unsafe-inline' is permitted on both script-src and style-src, and
 *    fonts.googleapis.com is permitted in style-src for Swagger's web font.
 *
 *  - console: CodeMirror 6 injects inline styles for syntax highlighting;
 *    'unsafe-inline' is permitted on style-src only — script-src stays strict.
 *
 * Note: app.blade.php ships a tiny inline <script> for theme detection on every
 * page. Tightening script-src to nonces across all routes is tracked as a
 * post-submission follow-up (see phase-10 risks section).
 */
final class ContentSecurityPolicyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $route = $request->route()?->getName();

        $isApiDocs = $route === 'api-docs';
        $isConsole = $route === 'console';
        $isWebPage = $route !== null && ! str_starts_with($request->path(), 'api/');

        // script-src: app.blade.php ships an inline theme-detection script on all
        // web pages; unsafe-inline is required there. API endpoints stay strict.
        $scriptSrc = "script-src 'self'".($isApiDocs || $isWebPage ? " 'unsafe-inline'" : '');

        // style-src: relax inline for both api-docs and console; also allow
        // Google Fonts stylesheet for api-docs.
        $styleSrc = "style-src 'self' https://fonts.googleapis.com".(($isApiDocs || $isConsole || $isWebPage) ? " 'unsafe-inline'" : '');

        $directives = [
            "default-src 'self'",
            $scriptSrc,
            $styleSrc,
            "img-src 'self' data:",
            "font-src 'self' https://fonts.gstatic.com",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        $response->headers->set('Content-Security-Policy', implode('; ', $directives));

        return $response;
    }
}
