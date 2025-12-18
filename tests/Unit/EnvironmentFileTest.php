<?php

test('testing environment loads the dedicated .env.testing file', function (): void {
    expect($this)->toBeInstanceOf(Tests\TestCase::class);
    expect(app()->environment())->toBe('testing');
    expect(app()->environmentFile())->toBe('.env.testing');
});
