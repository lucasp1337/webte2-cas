<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ApiKeyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $key_prefix
 * @property string $key_hash
 * @property Carbon|null $last_used_at
 * @property Carbon|null $revoked_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static Builder<static> active()
 */
final class ApiKey extends Model
{
    /** @use HasFactory<ApiKeyFactory> */
    use HasFactory;

    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'key_prefix',
        'key_hash',
        'last_used_at',
        'revoked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public static function findByPlaintextKey(string $plaintext): ?self
    {
        $prefix = substr($plaintext, 0, 8);

        /** @var self|null $candidate */
        $candidate = self::query()
            ->where('key_prefix', $prefix)
            ->whereNull('revoked_at')
            ->first();

        if ($candidate === null) {
            return null;
        }

        /** @var string $appKey */
        $appKey = config('app.key');
        $expected = hash_hmac('sha256', $plaintext, $appKey);

        return hash_equals($candidate->key_hash, $expected) ? $candidate : null;
    }

    /**
     * @param  Builder<static>  $q
     */
    public function scopeActive(Builder $q): void
    {
        $q->whereNull('revoked_at');
    }
}
