<?php

declare(strict_types=1);

use VagKalosynakis\LeagueRouteAttributes\Attributes\WithoutMiddleware;

it('instantiates with middleware exclusion array', function () {
    $withoutMiddleware = new WithoutMiddleware(middlewareClasses: ['App\Middleware\AuthMiddleware']);

    expect($withoutMiddleware)->toBeInstanceOf(WithoutMiddleware::class);
});

it('stores single excluded middleware class', function () {
    $middlewareClass = 'App\Middleware\AuthMiddleware';
    $withoutMiddleware = new WithoutMiddleware(middlewareClasses: [$middlewareClass]);

    expect($withoutMiddleware->middlewareClasses)->toHaveCount(1)
        ->and($withoutMiddleware->middlewareClasses[0])->toBe($middlewareClass);
});

it('stores multiple excluded middleware classes', function () {
    $middlewareClasses = [
        'App\Middleware\AuthMiddleware',
        'App\Middleware\RateLimitMiddleware',
        'App\Middleware\LoggingMiddleware',
    ];
    $withoutMiddleware = new WithoutMiddleware(middlewareClasses: $middlewareClasses);

    expect($withoutMiddleware->middlewareClasses)->toBe($middlewareClasses)
        ->and($withoutMiddleware->middlewareClasses)->toHaveCount(3);
});

it('preserves order of excluded middleware classes', function () {
    $middlewareClasses = [
        'First',
        'Second',
        'Third',
    ];
    $withoutMiddleware = new WithoutMiddleware(middlewareClasses: $middlewareClasses);

    expect($withoutMiddleware->middlewareClasses[0])->toBe('First')
        ->and($withoutMiddleware->middlewareClasses[1])->toBe('Second')
        ->and($withoutMiddleware->middlewareClasses[2])->toBe('Third');
});

it('has public middlewareClasses property', function () {
    $withoutMiddleware = new WithoutMiddleware(middlewareClasses: ['TestMiddleware']);

    expect($withoutMiddleware->middlewareClasses)->toBeArray();
});

it('allows empty exclusion array', function () {
    $withoutMiddleware = new WithoutMiddleware(middlewareClasses: []);

    expect($withoutMiddleware->middlewareClasses)->toBeArray()
        ->and($withoutMiddleware->middlewareClasses)->toBeEmpty();
});

it('handles fully qualified class names', function () {
    $middlewareClasses = [
        'VagKalosynakis\LeagueRouteAttributes\Middleware\AuthMiddleware',
        'VagKalosynakis\LeagueRouteAttributes\Middleware\CorsMiddleware',
    ];
    $withoutMiddleware = new WithoutMiddleware(middlewareClasses: $middlewareClasses);

    expect($withoutMiddleware->middlewareClasses)->toBe($middlewareClasses);
});

it('allows duplicate middleware classes in exclusion array', function () {
    $middlewareClasses = [
        'App\Middleware\AuthMiddleware',
        'App\Middleware\AuthMiddleware',
    ];
    $withoutMiddleware = new WithoutMiddleware(middlewareClasses: $middlewareClasses);

    expect($withoutMiddleware->middlewareClasses)->toHaveCount(2)
        ->and($withoutMiddleware->middlewareClasses[0])->toBe('App\Middleware\AuthMiddleware')
        ->and($withoutMiddleware->middlewareClasses[1])->toBe('App\Middleware\AuthMiddleware');
});

it('can exclude the same middleware that was applied', function () {
    $middlewareClass = 'App\Middleware\AuthMiddleware';
    $withoutMiddleware = new WithoutMiddleware(middlewareClasses: [$middlewareClass]);

    expect($withoutMiddleware->middlewareClasses)->toContain($middlewareClass);
});
