<?php

declare(strict_types=1);

use App\Services\GeolocationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
});

describe('GeolocationService', function (): void {
    it('returns unknown for private/reserved/loopback IPs without calling the API', function (): void {
        Http::fake();

        $service = app(GeolocationService::class);

        foreach (['10.0.0.1', '192.168.1.100', '127.0.0.1', '::1', 'not-an-ip'] as $ip) {
            $result = $service->lookup($ip);

            expect($result->countryIso)->toBeNull()
                ->and($result->country)->toBeNull()
                ->and($result->city)->toBeNull();
        }

        Http::assertNothingSent();
    });

    it('maps a successful ip-api response to a GeolocationResult', function (): void {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => 'Germany',
                'countryCode' => 'DE',
                'city' => 'Berlin',
            ]),
        ]);

        $result = app(GeolocationService::class)->lookup('8.8.8.8');

        expect($result->countryIso)->toBe('DE')
            ->and($result->country)->toBe('Germany')
            ->and($result->city)->toBe('Berlin');
    });

    it('returns unknown when ip-api reports status fail', function (): void {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'fail',
                'message' => 'private range',
            ]),
        ]);

        $result = app(GeolocationService::class)->lookup('8.8.8.8');

        expect($result->countryIso)->toBeNull()
            ->and($result->country)->toBeNull()
            ->and($result->city)->toBeNull();
    });

    it('returns unknown when the API call throws or times out', function (): void {
        Http::fake(function (): never {
            throw new RuntimeException('connection timed out');
        });

        $result = app(GeolocationService::class)->lookup('8.8.8.8');

        expect($result->countryIso)->toBeNull()
            ->and($result->country)->toBeNull()
            ->and($result->city)->toBeNull();
    });

    it('caches a lookup so a repeated IP does not hit the API twice', function (): void {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => 'Germany',
                'countryCode' => 'DE',
                'city' => 'Berlin',
            ]),
        ]);

        $service = app(GeolocationService::class);

        $first = $service->lookup('8.8.8.8');
        $second = $service->lookup('8.8.8.8');

        expect($first->countryIso)->toBe('DE')
            ->and($second->countryIso)->toBe('DE');

        Http::assertSentCount(1);
    });
});
