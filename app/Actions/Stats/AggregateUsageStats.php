<?php

declare(strict_types=1);

namespace App\Actions\Stats;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class AggregateUsageStats
{
    private const int DAYS = 30;

    private const int TOP_COUNTRIES_LIMIT = 10;

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
                $animation = $row->animation;
                $c = $row->c;

                return [
                    is_string($animation) ? $animation : '' => is_numeric($c) ? (int) $c : 0,
                ];
            })
            ->all();

        $perDay = DB::table('animation_usages')
            ->select(DB::raw('date(started_at) as d'), 'animation', DB::raw('count(*) as c'))
            ->where('started_at', '>=', $since)
            ->groupBy('d', 'animation')
            ->orderBy('d')
            ->get()
            ->map(function (\stdClass $row): array {
                $d = $row->d;
                $animation = $row->animation;
                $c = $row->c;

                return [
                    'date' => is_string($d) ? $d : '',
                    'animation' => is_string($animation) ? $animation : '',
                    'count' => is_numeric($c) ? (int) $c : 0,
                ];
            })
            ->values()
            ->all();

        $topCountries = DB::table('animation_usages')
            ->select('country_iso', 'country', DB::raw('count(*) as c'))
            ->whereNotNull('country_iso')
            ->where('started_at', '>=', $since)
            ->groupBy('country_iso', 'country')
            ->orderByDesc('c')
            ->limit(self::TOP_COUNTRIES_LIMIT)
            ->get()
            ->map(function (\stdClass $row): array {
                $countryIso = $row->country_iso;
                $country = $row->country;
                $c = $row->c;

                return [
                    'country_iso' => is_string($countryIso) ? $countryIso : '',
                    'country' => is_string($country) ? $country : null,
                    'count' => is_numeric($c) ? (int) $c : 0,
                ];
            })
            ->values()
            ->all();

        return [
            'totals' => $totals,
            'per_day' => $perDay,
            'top_countries' => $topCountries,
        ];
    }
}
