<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AnimationName;
use App\Models\AnimationUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnimationUsage>
 */
final class AnimationUsageFactory extends Factory
{
    protected $model = AnimationUsage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var AnimationName $animation */
        $animation = fake()->randomElement(AnimationName::cases());

        return [
            'animation' => $animation->value,
            'anon_token' => fake()->uuid(),
            'started_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'country_iso' => fake()->optional(0.8)->countryCode(),
            'country' => fake()->optional(0.8)->country(),
            'city' => fake()->optional(0.7)->city(),
        ];
    }

    /**
     * State for a pendulum animation usage.
     */
    public function pendulum(): static
    {
        return $this->state(fn (array $attributes) => [
            'animation' => AnimationName::Pendulum->value,
        ]);
    }

    /**
     * State for a ball-beam animation usage.
     */
    public function ballBeam(): static
    {
        return $this->state(fn (array $attributes) => [
            'animation' => AnimationName::BallBeam->value,
        ]);
    }

    /**
     * State with no geolocation data.
     */
    public function withoutGeo(): static
    {
        return $this->state(fn (array $attributes) => [
            'country_iso' => null,
            'country' => null,
            'city' => null,
        ]);
    }

    /**
     * State with a specific country and optional city.
     */
    public function forCountry(string $iso, string $name, ?string $city = null): static
    {
        return $this->state(fn (array $attributes) => [
            'country_iso' => $iso,
            'country' => $name,
            'city' => $city,
        ]);
    }
}
