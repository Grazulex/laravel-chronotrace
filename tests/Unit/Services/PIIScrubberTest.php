<?php

use Grazulex\LaravelChronotrace\Services\PIIScrubber;

beforeEach(function (): void {
    config(['chronotrace.scrub' => ['password', 'token', 'secret']]);
    config(['chronotrace.scrub_patterns' => []]);
});

it('can scrub sensitive data from arrays', function (): void {
    $scrubber = app(PIIScrubber::class);

    $data = [
        'username' => 'john',
        'password' => 'secret123',
        'email' => 'john@example.com',
        'token' => 'abc123',
        'nested' => [
            'secret' => 'hidden',
            'public' => 'visible',
        ],
    ];

    $scrubbed = $scrubber->scrubArray($data);

    expect($scrubbed['username'])->toBe('john');
    expect($scrubbed['password'])->toBe('[SCRUBBED]');
    expect($scrubbed['email'])->toBe('john@example.com');
    expect($scrubbed['token'])->toBe('[SCRUBBED]');
    expect($scrubbed['nested']['secret'])->toBe('[SCRUBBED]');
    expect($scrubbed['nested']['public'])->toBe('visible');
});

it('can scrub sensitive data from strings with patterns', function (): void {
    config(['chronotrace.scrub_patterns' => [
        '/password=\S+/',
        '/token=\S+/',
    ]]);

    $scrubber = app(PIIScrubber::class);

    $data = 'This contains a password=secret123 and token=abc123';
    $scrubbed = $scrubber->scrubString($data);

    expect($scrubbed)->toContain('[SCRUBBED]');
    expect($scrubbed)->not->toContain('secret123');
    expect($scrubbed)->not->toContain('abc123');
});
it('handles empty data gracefully', function (): void {
    $scrubber = app(PIIScrubber::class);

    expect($scrubber->scrubArray([]))->toBe([]);
    expect($scrubber->scrubString(''))->toBe('');
});
it('preserves non-sensitive data', function (): void {
    $scrubber = app(PIIScrubber::class);

    $data = [
        'id' => 123,
        'name' => 'John Doe',
        'status' => 'active',
        'created_at' => '2024-01-01',
    ];

    $scrubbed = $scrubber->scrubArray($data);

    expect($scrubbed)->toBe($data);
});

it('can instantiate service', function (): void {
    $scrubber = app(PIIScrubber::class);
    expect($scrubber)->toBeInstanceOf(PIIScrubber::class);
});

it('scrubs case insensitive keys', function (): void {
    $scrubber = app(PIIScrubber::class);

    $data = [
        'PASSWORD' => 'secret',
        'Token' => 'abc123',
        'SECRET' => 'hidden',
    ];

    $scrubbed = $scrubber->scrubArray($data);

    expect($scrubbed['PASSWORD'])->toBe('[SCRUBBED]');
    expect($scrubbed['Token'])->toBe('[SCRUBBED]');
    expect($scrubbed['SECRET'])->toBe('[SCRUBBED]');
});

it('handles nested arrays deeply', function (): void {
    $scrubber = app(PIIScrubber::class);

    $data = [
        'level1' => [
            'level2' => [
                'level3' => [
                    'password' => 'deep_secret',
                ],
            ],
        ],
    ];

    $scrubbed = $scrubber->scrubArray($data);

    expect($scrubbed['level1']['level2']['level3']['password'])->toBe('[SCRUBBED]');
});
