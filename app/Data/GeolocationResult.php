<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class GeolocationResult extends Data
{
    public function __construct(
        public readonly ?string $countryIso,
        public readonly ?string $country,
        public readonly ?string $city,
    ) {}

    /**
     * Returns a GeolocationResult with all fields null, used when the IP
     * is private/reserved or the GeoIP2 lookup fails.
     */
    public static function unknown(): self
    {
        return new self(
            countryIso: null,
            country: null,
            city: null,
        );
    }
}
