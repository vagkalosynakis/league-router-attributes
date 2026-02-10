<?php

declare(strict_types=1);

use VagKalosynakis\LeagueRouteAttributes\Attributes\Middleware;

it('instantiates with middleware class array', function () {
    $middleware = new Middleware(middlewareClasses: ['App\Middleware\AuthMiddleware']);

    expect($middleware)->toBeInstanceOf(Middleware::class);
});

it('stores single middleware class', function () {
    $middlewareClass = 'App\Middleware\AuthMiddleware';
    $middleware = new Middleware(middlewareClasses: [$middlewareClass]);

    expect($middleware->middlewareClasses)->toHaveCount(1)
        ->and($middleware->middlewareClasses[0])->toBe($middlewareClass);
});

it('stores multiple middleware classes', function () {
    $middlewareClasses = [
        'App\Middleware\AuthMiddleware',
        'App\Middleware\RateLimitMiddleware',
        'App\Middleware\LoggingMiddleware',
    ];
    $middleware = new Middleware(middlewareClasses: $middlewareClasses);

    expect($middleware->middlewareClasses)->toBe($middlewareClasses)
        ->and($middleware->middlewareClasses)->toHaveCount(3);
});

it('preserves order of middleware classes', function () {
    $middlewareClasses = [
        'First',
        'Second',
        'Third',
    ];
    $middleware = new Middleware(middlewareClasses: $middlewareClasses);

    expect($middleware->middlewareClasses[0])->toBe('First')
        ->and($middleware->middlewareClasses[1])->toBe('Second')
        ->and($middleware->middlewareClasses[2])->toBe('Third');
});

it('has public middlewareClasses property', function () {
    $middleware = new Middleware(middlewareClasses: ['TestMiddleware']);

    expect($middleware->middlewareClasses)->toBeArray();
});

it('allows empty middleware classes array', function () {
    $middleware = new Middleware(middlewareClasses: []);

    expect($middleware->middlewareClasses)->toBeArray()
        ->and($middleware->middlewareClasses)->toBeEmpty();
});

it('handles fully qualified class names', function () {
    $middlewareClasses = [
        'VagKalosynakis\LeagueRouteAttributes\Middleware\AuthMiddleware',
        'VagKalosynakis\LeagueRouteAttributes\Middleware\CorsMiddleware',
    ];
    $middleware = new Middleware(middlewareClasses: $middlewareClasses);

    expect($middleware->middlewareClasses)->toBe($middlewareClasses);
});

it('allows duplicate middleware classes in array', function () {
    $middlewareClasses = [
        'App\Middleware\AuthMiddleware',
        'App\Middleware\AuthMiddleware',
    ];
    $middleware = new Middleware(middlewareClasses: $middlewareClasses);

    expect($middleware->middlewareClasses)->toHaveCount(2)
        ->and($middleware->middlewareClasses[0])->toBe('App\Middleware\AuthMiddleware')
        ->and($middleware->middlewareClasses[1])->toBe('App\Middleware\AuthMiddleware');
});
