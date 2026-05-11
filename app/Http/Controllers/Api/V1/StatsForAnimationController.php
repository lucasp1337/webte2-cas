<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AnimationName;
use App\Http\Controllers\Controller;
use App\Http\Resources\AnimationStatsDetailResource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class StatsForAnimationController extends Controller
{
    public function __invoke(AnimationName $animation): AnimationStatsDetailResource
    {
        $since = CarbonImmutable::now()->subDays(30)->toDateTimeString();

        $totals = DB::table('animation_usages')
            ->select('animation', DB::raw('count(*) as c'))
            ->where('animation', $animation->value)
            ->where('started_at', '>=', $since)
            ->groupBy('animation')
            ->get()
            ->mapWithKeys(function (\stdClass $row): array {
                $anim = $row->animation;
                $c = $row->c;

                return [
                    is_string($anim) ? $anim : '' => is_numeric($c) ? (int) $c : 0,
                ];
            })
            ->all();

        $perDay = DB::table('animation_usages')
            ->select(DB::raw('date(started_at) as d'), 'animation', DB::raw('count(*) as c'))
            ->where('animation', $animation->value)
            ->where('started_at', '>=', $since)
            ->groupBy('d', 'animation')
            ->orderBy('d')
            ->get()
            ->map(function (\stdClass $row): array {
                $d = $row->d;
                $anim = $row->animation;
                $c = $row->c;

                return [
                    'date' => is_string($d) ? $d : '',
                    'animation' => is_string($anim) ? $anim : '',
                    'count' => is_numeric($c) ? (int) $c : 0,
                ];
            })
            ->all();

        $topCountries = DB::table('animation_usages')
            ->select('country_iso', 'country', DB::raw('count(*) as c'))
            ->where('animation', $animation->value)
            ->whereNotNull('country_iso')
            ->where('started_at', '>=', $since)
            ->groupBy('country_iso', 'country')
            ->orderByDesc('c')
            ->limit(10)
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
            ->all();

        $topCities = DB::table('animation_usages')
            ->select('city', DB::raw('count(*) as c'))
            ->where('animation', $animation->value)
            ->whereNotNull('city')
            ->where('started_at', '>=', $since)
            ->groupBy('city')
            ->orderByDesc('c')
            ->limit(10)
            ->get()
            ->map(function (\stdClass $row): array {
                $city = $row->city;
                $c = $row->c;

                return [
                    'city' => is_string($city) ? $city : '',
                    'count' => is_numeric($c) ? (int) $c : 0,
                ];
            })
            ->all();

        return AnimationStatsDetailResource::make([
            'animation' => $animation->value,
            'totals' => $totals,
            'per_day' => $perDay,
            'top_countries' => $topCountries,
            'top_cities' => $topCities,
        ]);
    }
}
