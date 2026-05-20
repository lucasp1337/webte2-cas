<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\GeolocationResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final readonly class GeolocationService
{
    /**
     * Cache TTL (seconds) for a successful lookup.
     */
    private const int CACHE_TTL_SUCCESS = 86_400;

    /**
     * Cache TTL (seconds) for an unknown result. Kept short so a transient
     * API failure does not pin an IP to "unknown" for a whole day.
     */
    private const int CACHE_TTL_UNKNOWN = 3_600;

    /**
     * Resolves an IP address to a GeolocationResult via the ip-api.com HTTP API.
     *
     * Returns GeolocationResult::unknown() when:
     *  - the IP is private, loopback, reserved, or malformed
     *  - the HTTP call fails, times out, or returns a non-200 response
     *  - ip-api.com reports a non-"success" status
     */
    public function lookup(string $ip): GeolocationResult
    {
        if ($this->shouldSkipLookup($ip)) {
            return GeolocationResult::unknown();
        }

        $cacheKey = "geolocation:{$ip}";

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return new GeolocationResult(
                countryIso: $this->stringOrNull($cached['countryIso'] ?? null),
                country: $this->stringOrNull($cached['country'] ?? null),
                city: $this->stringOrNull($cached['city'] ?? null),
            );
        }

        $result = $this->fetch($ip);

        $isUnknown = $result->countryIso === null
            && $result->country === null
            && $result->city === null;

        Cache::put(
            $cacheKey,
            [
                'countryIso' => $result->countryIso,
                'country' => $result->country,
                'city' => $result->city,
            ],
            $isUnknown ? self::CACHE_TTL_UNKNOWN : self::CACHE_TTL_SUCCESS,
        );

        return $result;
    }

    /**
     * Performs the ip-api.com HTTP request and maps the response.
     */
    private function fetch(string $ip): GeolocationResult
    {
        /** @var string $endpoint */
        $endpoint = config('cas.geolocation_endpoint', 'http://ip-api.com/json');
        $url = rtrim($endpoint, '/').'/'.$ip;

        try {
            $response = Http::timeout(3)->get($url);

            if (! $response->successful()) {
                return GeolocationResult::unknown();
            }

            /** @var array<string, mixed> $body */
            $body = $response->json();

            if (($body['status'] ?? null) !== 'success') {
                return GeolocationResult::unknown();
            }

            return new GeolocationResult(
                countryIso: $this->stringOrNull($body['countryCode'] ?? null),
                country: $this->stringOrNull($body['country'] ?? null),
                city: $this->stringOrNull($body['city'] ?? null),
            );
        } catch (\Throwable) {
            return GeolocationResult::unknown();
        }
    }

    /**
     * Narrows a loosely-typed response field to a non-empty string or null.
     */
    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Returns true for private, reserved, loopback, or malformed IPs.
     * These are never sent to the geolocation API.
     */
    private function shouldSkipLookup(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
