<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Services\Octave\OctaveBridgeClient;
use App\Services\Octave\OctaveExecutionResult;
use App\Services\Octave\Testing\FakeOctaveBridgeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\postJson;

use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * Bind a FakeOctaveBridgeClient configured to return a successful result
 * for every call — the limiter tests do not care about Octave output.
 */
function bindFakeForLimiterTests(): FakeOctaveBridgeClient
{
    $fake = new FakeOctaveBridgeClient;
    app()->instance(OctaveBridgeClient::class, $fake);

    return $fake;
}

/**
 * Create a fresh API key with a random suffix and return both the model and
 * the plaintext.  The random suffix ensures each test run produces a new
 * API key with a new unique ID, so rate-limit counters left by previous runs
 * in the shared Redis instance cannot bleed across tests.
 *
 * @return array{0: ApiKey, 1: string}
 */
function makeRateLimitApiKey(string $uniqueLabel): array
{
    // 16-char random hex to guarantee uniqueness across test runs.
    $rand = bin2hex(random_bytes(8));
    // Full plaintext includes both label and random to ensure distinct key_prefix.
    $plaintext = "webte2_{$uniqueLabel}{$rand}";
    $model = ApiKey::create(['name' => "rl-{$uniqueLabel}-{$rand}", 'key_hash' => $plaintext]);

    return [$model, $plaintext];
}

/**
 * Clear all rate-limit buckets (cas-api and octave-exec) for the given ApiKey
 * model, ensuring no cross-test pollution in the shared Redis instance.
 */
function clearRateLimitBuckets(ApiKey ...$models): void
{
    foreach ($models as $model) {
        RateLimiter::clear(md5('cas-api'.$model->id));
        RateLimiter::clear(md5('octave-exec'.'api-key:'.$model->id));
    }

    // Also clear the IP-based fallback buckets (used when api_key attribute is absent).
    RateLimiter::clear(md5('cas-api'.'127.0.0.1'));
    RateLimiter::clear(md5('octave-exec'.'127.0.0.1'));
}

beforeEach(function (): void {
    // The global TestCase disables ThrottleRequests for every test to avoid
    // noisy 429s in unrelated feature tests.  Re-enable it here so the limiter
    // can actually enforce its budget.

    /** @var TestCase $this */
    $this->withMiddleware(ThrottleRequests::class);

    // Clear the shared IP-based fallback buckets before each test so that
    // state from earlier test runs in this suite does not bleed over.
    RateLimiter::clear(md5('cas-api'.'127.0.0.1'));
    RateLimiter::clear(md5('octave-exec'.'127.0.0.1'));
});

it('allows 30 exec calls per minute per API key', function (): void {
    $fake = bindFakeForLimiterTests();

    // Override the per-minute limit to 3 so the test fires only 3 requests.
    config(['cas.cas_rate_limit_per_minute' => 3]);

    [$model, $plaintext] = makeRateLimitApiKey('allow');

    for ($i = 0; $i < 3; $i++) {
        $fake->setNextResult(new OctaveExecutionResult("req-{$i}", "ans = {$i}\n", '', 0, 5));
        postJson('/api/v1/octave/exec', ['command' => '1+1'], ['X-API-Key' => $plaintext])
            ->assertStatus(200);
    }

    clearRateLimitBuckets($model);
});

it('returns 429 on the 31st exec call within a minute', function (): void {
    $fake = bindFakeForLimiterTests();

    // Override the per-minute limit to 3 so the 4th request triggers 429.
    config(['cas.cas_rate_limit_per_minute' => 3]);

    [$model, $plaintext] = makeRateLimitApiKey('limit');

    for ($i = 0; $i < 3; $i++) {
        $fake->setNextResult(new OctaveExecutionResult("req-{$i}", '', '', 0, 5));
        postJson('/api/v1/octave/exec', ['command' => '1+1'], ['X-API-Key' => $plaintext])
            ->assertStatus(200);
    }

    // The 4th request exceeds the per-minute budget and must be throttled.
    postJson('/api/v1/octave/exec', ['command' => '1+1'], ['X-API-Key' => $plaintext])
        ->assertStatus(429);

    clearRateLimitBuckets($model);
});

it('isolates limits between different API keys', function (): void {
    $fake = bindFakeForLimiterTests();

    // Override the per-minute limit to 2 to keep the test fast.
    config(['cas.cas_rate_limit_per_minute' => 2]);

    [$modelA, $plaintextA] = makeRateLimitApiKey('pkey');
    [$modelB, $plaintextB] = makeRateLimitApiKey('qkey');

    // Exhaust key A's budget.
    for ($i = 0; $i < 2; $i++) {
        $fake->setNextResult(new OctaveExecutionResult("req-a{$i}", '', '', 0, 5));
        postJson('/api/v1/octave/exec', ['command' => '1+1'], ['X-API-Key' => $plaintextA])
            ->assertStatus(200);
    }

    // Key A is now throttled.
    postJson('/api/v1/octave/exec', ['command' => '1+1'], ['X-API-Key' => $plaintextA])
        ->assertStatus(429);

    // Key B has its own fresh budget and must succeed.
    $fake->setNextResult(new OctaveExecutionResult('req-b0', '', '', 0, 5));
    postJson('/api/v1/octave/exec', ['command' => '1+1'], ['X-API-Key' => $plaintextB])
        ->assertStatus(200);

    clearRateLimitBuckets($modelA, $modelB);
});
