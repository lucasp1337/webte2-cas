<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RequestLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $api_key_id
 * @property string $method
 * @property string $route
 * @property string $path
 * @property int $status
 * @property bool $success
 * @property int $duration_ms
 * @property string $ip_hash
 * @property string|null $user_agent
 * @property string|null $command
 * @property array<string, mixed>|null $parameters
 * @property Carbon $created_at
 *
 * @method static Builder<static> successful()
 * @method static Builder<static> forApiKey(string $apiKeyId)
 * @method static Builder<static> betweenDates(?Carbon $from, ?Carbon $to)
 * @method static Builder<static> forRoute(string $route)
 * @method static Builder<static> withStatus(int $code)
 */
final class RequestLog extends Model
{
    /** @use HasFactory<RequestLogFactory> */
    use HasFactory;

    use HasUlids;

    /** No updated_at column on this table. */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'id',
        'api_key_id',
        'method',
        'route',
        'path',
        'status',
        'success',
        'duration_ms',
        'ip_hash',
        'user_agent',
        'command',
        'parameters',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'success' => 'bool',
            'parameters' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<static>  $q
     */
    public function scopeSuccessful(Builder $q): void
    {
        $q->where('success', true);
    }

    /**
     * @param  Builder<static>  $q
     */
    public function scopeForApiKey(Builder $q, string $apiKeyId): void
    {
        $q->where('api_key_id', $apiKeyId);
    }

    /**
     * @param  Builder<static>  $q
     */
    public function scopeBetweenDates(Builder $q, ?Carbon $from, ?Carbon $to): void
    {
        if ($from !== null) {
            $q->where('created_at', '>=', $from);
        }

        if ($to !== null) {
            $q->where('created_at', '<=', $to);
        }
    }

    /**
     * @param  Builder<static>  $q
     */
    public function scopeForRoute(Builder $q, string $route): void
    {
        $q->where('route', $route);
    }

    /**
     * @param  Builder<static>  $q
     */
    public function scopeWithStatus(Builder $q, int $code): void
    {
        $q->where('status', $code);
    }
}
