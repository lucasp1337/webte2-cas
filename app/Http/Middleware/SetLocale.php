<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    private const string DEFAULT_LOCALE = 'sk';

    /** @var list<string> */
    private const array SUPPORTED_LOCALES = ['sk', 'en'];

    private const int COOKIE_LIFETIME_MINUTES = 60 * 24 * 365;

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);
        Carbon::setLocale($locale);

        $request->session()->put('locale', $locale);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->setCookie(cookie('locale', $locale, self::COOKIE_LIFETIME_MINUTES));

        return $response;
    }

    private function resolveLocale(Request $request): string
    {
        /** @var string|null $routeLocale */
        $routeLocale = $request->route('locale');
        if ($routeLocale !== null && $this->isSupported($routeLocale)) {
            return $routeLocale;
        }

        /** @var string|null $session */
        $session = $request->session()->get('locale');
        if ($session !== null && $this->isSupported($session)) {
            return $session;
        }

        /** @var string|null $cookie */
        $cookie = $request->cookie('locale');
        if (is_string($cookie) && $this->isSupported($cookie)) {
            return $cookie;
        }

        $preferred = $request->getPreferredLanguage(self::SUPPORTED_LOCALES);
        if (is_string($preferred) && $this->isSupported($preferred)) {
            return $preferred;
        }

        return self::DEFAULT_LOCALE;
    }

    private function isSupported(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED_LOCALES, true);
    }
}
