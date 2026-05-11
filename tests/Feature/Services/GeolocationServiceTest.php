<?php

declare(strict_types=1);

use App\Services\GeolocationService;
use GeoIp2\Database\Reader;
use GeoIp2\Model\City;
use Mockery\Expectation;
use Mockery\MockInterface;

describe('GeolocationService', function (): void {
    it('returns unknown when reader is null', function (): void {
        $service = new GeolocationService(null);

        $result = $service->lookup('8.8.8.8');

        expect($result->countryIso)->toBeNull()
            ->and($result->country)->toBeNull()
            ->and($result->city)->toBeNull();
    });

    it('returns unknown for loopback IPv4', function (): void {
        /** @var Reader&MockInterface $reader */
        $reader = Mockery::mock(Reader::class);
        $service = new GeolocationService($reader);

        $result = $service->lookup('127.0.0.1');

        expect($result->countryIso)->toBeNull();
    });

    it('returns unknown for loopback IPv6', function (): void {
        /** @var Reader&MockInterface $reader */
        $reader = Mockery::mock(Reader::class);
        $service = new GeolocationService($reader);

        $result = $service->lookup('::1');

        expect($result->countryIso)->toBeNull();
    });

    it('returns unknown for RFC1918 10.x address', function (): void {
        /** @var Reader&MockInterface $reader */
        $reader = Mockery::mock(Reader::class);
        $service = new GeolocationService($reader);

        $result = $service->lookup('10.0.0.1');

        expect($result->countryIso)->toBeNull();
    });

    it('returns unknown for RFC1918 192.168.x address', function (): void {
        /** @var Reader&MockInterface $reader */
        $reader = Mockery::mock(Reader::class);
        $service = new GeolocationService($reader);

        $result = $service->lookup('192.168.1.100');

        expect($result->countryIso)->toBeNull();
    });

    it('returns unknown for RFC1918 172.16.x address', function (): void {
        /** @var Reader&MockInterface $reader */
        $reader = Mockery::mock(Reader::class);
        $service = new GeolocationService($reader);

        $result = $service->lookup('172.16.0.1');

        expect($result->countryIso)->toBeNull();
    });

    it('returns unknown for RFC1918 172.31.x address', function (): void {
        /** @var Reader&MockInterface $reader */
        $reader = Mockery::mock(Reader::class);
        $service = new GeolocationService($reader);

        $result = $service->lookup('172.31.255.255');

        expect($result->countryIso)->toBeNull();
    });

    it('returns unknown when reader throws an exception', function (): void {
        /** @var Reader&MockInterface $reader */
        $reader = Mockery::mock(Reader::class);
        /** @var Expectation $exp */
        $exp = $reader->shouldReceive('city');
        $exp->andThrow(new RuntimeException('DB not found'));
        $service = new GeolocationService($reader);

        $result = $service->lookup('8.8.8.8');

        expect($result->countryIso)->toBeNull()
            ->and($result->country)->toBeNull()
            ->and($result->city)->toBeNull();
    });

    it('returns populated result for a public IP', function (): void {
        // Build a minimal GeoIp2 City model with enough data to exercise the mapping.
        $cityModel = new City([
            'country' => ['iso_code' => 'DE', 'names' => ['en' => 'Germany']],
            'city' => ['names' => ['en' => 'Berlin']],
            'traits' => ['ip_address' => '8.8.8.8'],
        ], ['en']);

        /** @var Reader&MockInterface $reader */
        $reader = Mockery::mock(Reader::class);
        /** @var Expectation $exp */
        $exp = $reader->shouldReceive('city');
        $exp->with('8.8.8.8')->andReturn($cityModel);

        $service = new GeolocationService($reader);
        $result = $service->lookup('8.8.8.8');

        expect($result->countryIso)->toBe('DE')
            ->and($result->country)->toBe('Germany')
            ->and($result->city)->toBe('Berlin');
    });
});
