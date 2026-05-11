<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\GeolocationResult;
use GeoIp2\Database\Reader;

final readonly class GeolocationService
{
    public function __construct(private ?Reader $reader) {}

    /**
     * Resolves an IP address to a GeolocationResult.
     *
     * Returns GeolocationResult::unknown() when:
     *  - the GeoIP2 Reader is unavailable (DB file missing)
     *  - the IP is private, loopback, or otherwise reserved
     *  - the Reader throws any exception
     */
    public function lookup(string $ip): GeolocationResult
    {
        if ($this->reader === null) {
            return GeolocationResult::unknown();
        }

        if ($this->shouldSkipLookup($ip)) {
            return GeolocationResult::unknown();
        }

        try {
            $record = $this->reader->city($ip);

            return new GeolocationResult(
                countryIso: $record->country->isoCode,
                country: $record->country->name,
                city: $record->city->name,
            );
        } catch (\Throwable) {
            return GeolocationResult::unknown();
        }
    }

    /**
     * Returns true for private, reserved, loopback, or malformed IPs.
     * These should not be sent to the GeoIP2 database.
     */
    private function shouldSkipLookup(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
