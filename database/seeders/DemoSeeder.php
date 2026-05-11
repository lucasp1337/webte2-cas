<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AnimationName;
use App\Models\AnimationUsage;
use App\Models\ApiKey;
use App\Models\RequestLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class DemoSeeder extends Seeder
{
    /** @var int Minimum rows required before skipping the bulk insert. */
    private const MIN_REQUEST_LOGS = 50;

    /** @var int Minimum rows required before skipping the bulk insert. */
    private const MIN_ANIMATION_USAGES = 100;

    /** @var int Spread request logs across this many days. */
    private const REQUEST_LOG_DAYS = 14;

    /** @var int Spread animation usages across this many days. */
    private const ANIMATION_USAGE_DAYS = 30;

    /** @var int Number of distinct anon tokens to cycle through. */
    private const ANON_TOKEN_POOL_SIZE = 12;

    public function run(): void
    {
        $this->seedApiKey();
        $this->seedRequestLogs();
        $this->seedAnimationUsages();
    }

    private function seedApiKey(): void
    {
        /** @var string|null $plaintext */
        $plaintext = config('cas.api_key_plaintext');

        if ($plaintext === null || $plaintext === '') {
            $this->command->warn('CAS_API_KEY_PLAINTEXT is not set — skipping demo API key creation.');

            return;
        }

        $prefix = substr($plaintext, 0, 8);

        if (ApiKey::query()->where('key_prefix', $prefix)->exists()) {
            $this->command->info('Demo API key already exists — skipping.');

            return;
        }

        // The ApiKeyObserver hashes key_hash on creating.
        ApiKey::create([
            'name' => 'Demo key',
            'key_hash' => $plaintext,
        ]);

        $this->command->info('Demo API key created successfully.');
    }

    private function seedRequestLogs(): void
    {
        if (RequestLog::count() >= self::MIN_REQUEST_LOGS) {
            $this->command->info('Request logs already seeded — skipping.');

            return;
        }

        $apiKeyId = ApiKey::query()->value('id');

        $now = CarbonImmutable::now();

        /**
         * Weighted status code distribution: heavily towards 200, then 201,
         * with a tail of error codes for visual variety in the logs table.
         *
         * @var array<int, array{status: int, success: bool}> $statusPool
         */
        $statusPool = [
            ...array_fill(0, 30, ['status' => 200, 'success' => true]),
            ...array_fill(0, 8, ['status' => 201, 'success' => true]),
            ...array_fill(0, 5, ['status' => 401, 'success' => false]),
            ...array_fill(0, 4, ['status' => 422, 'success' => false]),
            ...array_fill(0, 3, ['status' => 503, 'success' => false]),
        ];

        /**
         * Endpoint distribution for non-exec routes.
         *
         * @var array<int, array{route: string, path: string, method: string}> $endpointPool
         */
        $endpointPool = [
            ...array_fill(0, 20, ['route' => 'octave.exec', 'path' => '/api/v1/octave/exec', 'method' => 'POST']),
            ...array_fill(0, 12, ['route' => 'simulations.pendulum', 'path' => '/api/v1/simulations/pendulum', 'method' => 'POST']),
            ...array_fill(0, 8, ['route' => 'simulations.ball-beam', 'path' => '/api/v1/simulations/ball-beam', 'method' => 'POST']),
            ...array_fill(0, 6, ['route' => 'logs.index', 'path' => '/api/v1/logs', 'method' => 'GET']),
            ...array_fill(0, 4, ['route' => 'stats.index', 'path' => '/api/v1/stats', 'method' => 'GET']),
        ];

        /** @var list<string> $octaveCommands */
        $octaveCommands = [
            'A = [1 2; 3 4]; eig(A)',
            'x = linspace(0, 2*pi, 100); sum(sin(x))',
            'for i = 1:5; disp(i^2); end',
            'M = magic(4); det(M)',
            'A = rand(3); inv(A)',
            'fib = [1 1]; for k=3:10; fib(k)=fib(k-1)+fib(k-2); end; fib',
            'x = 0:0.1:10; trapz(x, exp(-x))',
            'ode45(@(t,y) -y, [0 5], 1)',
            'roots([1 -3 2])',
            'A = hilb(5); cond(A)',
        ];

        $spreadSeconds = self::REQUEST_LOG_DAYS * 24 * 3600;

        RequestLog::factory()
            ->count(self::MIN_REQUEST_LOGS)
            ->sequence(function (/** @var Sequence $seq */ $seq) use (
                $now,
                $spreadSeconds,
                $statusPool,
                $endpointPool,
                $octaveCommands,
                $apiKeyId,
            ): array {
                $statusRow = $statusPool[array_rand($statusPool)];
                $endpointRow = $endpointPool[array_rand($endpointPool)];
                $isExec = $endpointRow['route'] === 'octave.exec';

                return [
                    'api_key_id' => $apiKeyId,
                    'status' => $statusRow['status'],
                    'success' => $statusRow['success'],
                    'route' => $endpointRow['route'],
                    'path' => $endpointRow['path'],
                    'method' => $endpointRow['method'],
                    'command' => $isExec
                        ? substr($octaveCommands[array_rand($octaveCommands)], 0, 1024)
                        : null,
                    'created_at' => $now->subSeconds(random_int(1, $spreadSeconds)),
                ];
            })
            ->create();

        $this->command->info('Seeded '.self::MIN_REQUEST_LOGS.' request logs.');
    }

    private function seedAnimationUsages(): void
    {
        if (AnimationUsage::count() >= self::MIN_ANIMATION_USAGES) {
            $this->command->info('Animation usages already seeded — skipping.');

            return;
        }

        /**
         * A small pool of anon tokens to simulate real cooldown patterns where
         * the same visitor runs multiple simulations.
         *
         * @var list<string> $anonTokens
         */
        $anonTokens = array_map(
            static fn (): string => (string) Str::uuid(),
            range(1, self::ANON_TOKEN_POOL_SIZE),
        );

        /**
         * Realistic geo distribution: country ISO, country name, cities.
         *
         * @var list<array{iso: string, name: string, cities: list<string|null>}> $geoPool
         */
        $geoPool = [
            ['iso' => 'DE', 'name' => 'Germany', 'cities' => ['Berlin', 'Munich', 'Hamburg']],
            ['iso' => 'US', 'name' => 'United States', 'cities' => ['New York', 'San Francisco', 'Chicago']],
            ['iso' => 'SK', 'name' => 'Slovakia', 'cities' => ['Bratislava', 'Košice', 'Žilina']],
            ['iso' => 'CZ', 'name' => 'Czechia', 'cities' => ['Prague', 'Brno', 'Ostrava']],
            ['iso' => 'JP', 'name' => 'Japan', 'cities' => ['Tokyo', 'Osaka', 'Kyoto']],
            ['iso' => 'BR', 'name' => 'Brazil', 'cities' => ['São Paulo', 'Rio de Janeiro', 'Curitiba']],
            // null geo to exercise the observer's empty-geo path
            ['iso' => '', 'name' => '', 'cities' => [null]],
        ];

        /**
         * Weighted animation distribution: ~55 pendulum / ~45 ball-beam.
         *
         * @var list<string> $animationPool
         */
        $animationPool = [
            ...array_fill(0, 55, AnimationName::Pendulum->value),
            ...array_fill(0, 45, AnimationName::BallBeam->value),
        ];

        $spreadSeconds = self::ANIMATION_USAGE_DAYS * 24 * 3600;

        AnimationUsage::factory()
            ->count(self::MIN_ANIMATION_USAGES)
            ->sequence(function (/** @var Sequence $seq */ $seq) use (
                $anonTokens,
                $geoPool,
                $animationPool,
                $spreadSeconds,
            ): array {
                /** @var array{iso: string, name: string, cities: list<string|null>} $geoEntry */
                $geoEntry = $geoPool[array_rand($geoPool)];
                $city = $geoEntry['cities'][array_rand($geoEntry['cities'])];
                $isNullGeo = $geoEntry['iso'] === '';
                $started = CarbonImmutable::now()->subSeconds(random_int(1, $spreadSeconds));

                return [
                    'animation' => $animationPool[array_rand($animationPool)],
                    'anon_token' => $anonTokens[array_rand($anonTokens)],
                    'started_at' => $started,
                    'country_iso' => $isNullGeo ? null : $geoEntry['iso'],
                    'country' => $isNullGeo ? null : $geoEntry['name'],
                    'city' => $isNullGeo ? null : $city,
                ];
            })
            ->create();

        $this->command->info('Seeded '.self::MIN_ANIMATION_USAGES.' animation usages.');
    }
}
