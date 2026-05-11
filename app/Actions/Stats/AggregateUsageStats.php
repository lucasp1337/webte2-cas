<?php

declare(strict_types=1);

namespace App\Actions\Stats;

use App\Enums\AnimationName;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class AggregateUsageStats
{
    private const int DAYS = 30;

    private const int TOP_COUNTRIES_LIMIT = 10;

    private const int TOP_CITIES_LIMIT = 10;

    /**
     * @return array{totals: array<string, int>, per_day: array<int, array{date: string, animation: string, count: int}>, top_countries: array<int, array{country_iso: string, country: string|null, count: int}>}
     */
    public function handle(): array
    {
        $since = CarbonImmutable::now()->subDays(self::DAYS)->toDateTimeString();

        $totals = DB::table('animation_usages')
            ->select('animation', DB::raw('count(*) as c'))
            ->where('started_at', '>=', $since)
            ->groupBy('animation')
            ->get()
            ->mapWithKeys(function (\stdClass $row): array {
                return [
                    $this->str($row, 'animation') => $this->int($row, 'c'),
                ];
            })
            ->all();

        $perDay = DB::table('animation_usages')
            ->select(DB::raw('date(started_at) as d'), 'animation', DB::raw('count(*) as c'))
            ->where('started_at', '>=', $since)
            ->groupBy('d', 'animation')
            ->orderBy('d')
            ->get()
            ->map(fn (\stdClass $row): array => [
                'date' => $this->str($row, 'd'),
                'animation' => $this->str($row, 'animation'),
                'count' => $this->int($row, 'c'),
            ])
            ->values()
            ->all();

        $topCountries = $this->queryTopCountries($since, null);

        return [
            'totals' => $totals,
            'per_day' => $perDay,
            'top_countries' => $topCountries,
        ];
    }

    /**
     * Per-animation drilldown: per-day, per-country, and per-city breakdown
     * for a single animation over the last 30 days.
     *
     * @return array{animation: string, per_day: array<int, array{date: string, count: int}>, top_countries: array<int, array{country_iso: string, country: string|null, count: int}>, top_cities: array<int, array{city: string|null, count: int}>}
     */
    public function handleForAnimation(AnimationName $animation): array
    {
        $since = CarbonImmutable::now()->subDays(self::DAYS)->toDateTimeString();
        $value = $animation->value;

        $perDay = DB::table('animation_usages')
            ->select(DB::raw('date(started_at) as d'), DB::raw('count(*) as c'))
            ->where('started_at', '>=', $since)
            ->where('animation', $value)
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->map(fn (\stdClass $row): array => [
                'date' => $this->str($row, 'd'),
                'count' => $this->int($row, 'c'),
            ])
            ->values()
            ->all();

        $topCountries = $this->queryTopCountries($since, $value);

        $topCities = DB::table('animation_usages')
            ->select('city', DB::raw('count(*) as c'))
            ->whereNotNull('city')
            ->where('started_at', '>=', $since)
            ->where('animation', $value)
            ->groupBy('city')
            ->orderByDesc('c')
            ->limit(self::TOP_CITIES_LIMIT)
            ->get()
            ->map(fn (\stdClass $row): array => [
                'city' => $this->nullable($row, 'city'),
                'count' => $this->int($row, 'c'),
            ])
            ->values()
            ->all();

        return [
            'animation' => $value,
            'per_day' => $perDay,
            'top_countries' => $topCountries,
            'top_cities' => $topCities,
        ];
    }

    /**
     * Shared top-countries query, optionally filtered to a single animation slug.
     *
     * @return array<int, array{country_iso: string, country: string|null, count: int}>
     */
    private function queryTopCountries(string $since, ?string $animationValue): array
    {
        $query = DB::table('animation_usages')
            ->select('country_iso', 'country', DB::raw('count(*) as c'))
            ->whereNotNull('country_iso')
            ->where('started_at', '>=', $since)
            ->groupBy('country_iso', 'country')
            ->orderByDesc('c')
            ->limit(self::TOP_COUNTRIES_LIMIT);

        if ($animationValue !== null) {
            $query->where('animation', $animationValue);
        }

        return $query
            ->get()
            ->map(fn (\stdClass $row): array => [
                'country_iso' => $this->str($row, 'country_iso'),
                'country' => $this->nullable($row, 'country'),
                'count' => $this->int($row, 'c'),
            ])
            ->values()
            ->all();
    }

    /** Coerce a stdClass column to a non-empty string (empty string as fallback). */
    private function str(\stdClass $row, string $col): string
    {
        $val = $row->{$col};

        return is_string($val) ? $val : '';
    }

    /** Coerce a stdClass column to int (0 as fallback). */
    private function int(\stdClass $row, string $col): int
    {
        $val = $row->{$col};

        return is_numeric($val) ? (int) $val : 0;
    }

    /** Coerce a stdClass column to string|null (null when absent or non-string). */
    private function nullable(\stdClass $row, string $col): ?string
    {
        $val = $row->{$col};

        return is_string($val) ? $val : null;
    }
}
