<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CI doesn't `npm run build`, so the Vite manifest doesn't exist on disk.
        $this->withoutVite();
    }
}
