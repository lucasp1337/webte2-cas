<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\RequestLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestLog>
 */
final class RequestLogFactory extends Factory
{
    protected $model = RequestLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement([200, 201, 400, 401, 404, 422, 500]);

        return [
            'api_key_id' => ApiKey::factory(),
            'method' => fake()->randomElement(['GET', 'POST', 'DELETE']),
            'route' => fake()->randomElement(['octave.exec', 'logs.index', 'simulations.pendulum']),
            'path' => fake()->randomElement(['/api/v1/octave/exec', '/api/v1/logs', '/api/v1/simulations/pendulum']),
            'status' => $status,
            'success' => $status < 400,
            'duration_ms' => fake()->numberBetween(5, 5000),
            'ip_hash' => (static function (): string {
                /** @var string $appKey */
                $appKey = config('app.key');

                return hash_hmac('sha256', '127.0.0.1', $appKey);
            })(),
            'user_agent' => fake()->userAgent(),
            'command' => null,
            'parameters' => null,
        ];
    }

    /**
     * State for a log with no associated API key.
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'api_key_id' => null,
        ]);
    }

    /**
     * State for a successful request.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 200,
            'success' => true,
        ]);
    }

    /**
     * State for a failed request.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 500,
            'success' => false,
        ]);
    }

    /**
     * State for an unauthorized request (401).
     */
    public function unauthorized(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 401,
            'success' => false,
        ]);
    }

    /**
     * State for a validation failure request (422).
     */
    public function validationFailure(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 422,
            'success' => false,
        ]);
    }

    /**
     * State for a bridge unavailable response (503).
     */
    public function bridgeUnavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 503,
            'success' => false,
        ]);
    }

    /**
     * State for an Octave exec request with a sample command payload.
     */
    public function octaveExec(): static
    {
        $commands = [
            'A = [1 2; 3 4]; eig(A)',
            'x = linspace(0, 2*pi, 100); sum(sin(x))',
            'for i = 1:5; disp(i^2); end',
            'M = magic(4); det(M)',
            'syms t; diff(sin(t^2), t)',
            'A = rand(3); inv(A)',
            'fib = [1 1]; for k=3:10; fib(k)=fib(k-1)+fib(k-2); end; fib',
            'x = 0:0.1:10; trapz(x, exp(-x))',
        ];

        return $this->state(function (array $attributes) use ($commands): array {
            /** @var string $cmd */
            $cmd = fake()->randomElement($commands);

            return [
                'route' => 'octave.exec',
                'path' => '/api/v1/octave/exec',
                'method' => 'POST',
                'command' => substr($cmd, 0, 200),
            ];
        });
    }
}
