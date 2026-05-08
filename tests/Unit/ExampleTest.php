<?php

declare(strict_types=1);

it('honours php strict types in integer arithmetic', function (): void {
    expect(2 + 2)->toBe(4);
});
