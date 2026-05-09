<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CI doesn't `npm run build`, so the Vite manifest doesn't exist on disk.
        $this->withoutVite();

        // Disable throttle middleware for all tests — rate limits are tested
        // at the middleware unit level, not in every feature test.
        $this->withoutMiddleware(ThrottleRequests::class);
    }
}
