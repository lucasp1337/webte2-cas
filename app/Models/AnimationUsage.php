<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnimationName;
use Carbon\CarbonImmutable;
use Database\Factories\AnimationUsageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property AnimationName $animation
 * @property string $anon_token
 * @property Carbon $started_at
 * @property string|null $country_iso
 * @property string|null $country
 * @property string|null $city
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static Builder<static> forAnimation(AnimationName $animation)
 * @method static Builder<static> recentForAnonToken(string $token, AnimationName $animation, CarbonImmutable $since)
 * @method static Builder<static> withinPeriod(CarbonImmutable $from, CarbonImmutable $to)
 */
final class AnimationUsage extends Model
{
    /** @use HasFactory<AnimationUsageFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'animation',
        'anon_token',
        'started_at',
        'country_iso',
        'country',
        'city',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'animation' => AnimationName::class,
            'started_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<static>  $q
     */
    public function scopeForAnimation(Builder $q, AnimationName $animation): void
    {
        $q->where('animation', $animation->value);
    }

    /**
     * @param  Builder<static>  $q
     */
    public function scopeRecentForAnonToken(Builder $q, string $token, AnimationName $animation, CarbonImmutable $since): void
    {
        $q->where('anon_token', $token)
            ->where('animation', $animation->value)
            ->where('started_at', '>=', $since);
    }

    /**
     * Filter rows whose started_at falls within the given half-open interval [$from, $to].
     *
     * @param  Builder<static>  $q
     */
    public function scopeWithinPeriod(Builder $q, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $q->where('started_at', '>=', $from)
            ->where('started_at', '<=', $to);
    }
}
