<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ExampleTest extends TestCase
{
    /**
     * Baseline sanity check — verifies PHP strict types are honoured.
     */
    public function test_strict_types_integer_arithmetic(): void
    {
        $this->assertSame(4, 2 + 2);
    }
}
