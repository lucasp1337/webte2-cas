<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiKey>
 */
final class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    /**
     * The model's default state.
     *
     * The observer hashes key_hash on creating and fills key_prefix,
     * so we set key_hash to the plaintext and leave key_prefix null.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plaintext = 'webte2_'.Str::random(48);

        return [
            'name' => fake()->company(),
            'key_hash' => $plaintext,   // observer will hash this
            'key_prefix' => null,        // observer will fill from plaintext
            'last_used_at' => null,
            'revoked_at' => null,
        ];
    }

    /**
     * State for a revoked key.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
        ]);
    }
}
