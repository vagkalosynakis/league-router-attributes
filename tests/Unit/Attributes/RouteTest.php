<?php

declare(strict_types=1);

use VagKalosynakis\LeagueRouteAttributes\Attributes\Route;

it('instantiates with valid parameters', function () {
    $route = new Route(
        method: ['GET', 'POST'],
        path: '/users',
        prefix: '/api',
        name: 'users.index',
    );

    expect($route)->toBeInstanceOf(Route::class);
});

it('stores HTTP methods array correctly', function () {
    $methods = ['GET', 'POST', 'PUT'];
    $route = new Route(method: $methods, path: '/users');

    expect($route->method)->toBe($methods)
        ->and($route->method)->toHaveCount(3)
        ->and($route->method)->toContain('GET')
        ->and($route->method)->toContain('POST')
        ->and($route->method)->toContain('PUT');
});

it('stores path correctly', function () {
    $path = '/users/{id:\d+}';
    $route = new Route(method: ['GET'], path: $path);

    expect($route->path)->toBe($path);
});

it('stores prefix when provided', function () {
    $prefix = '/api/v1';
    $route = new Route(method: ['GET'], path: '/users', prefix: $prefix);

    expect($route->prefix)->toBe($prefix);
});

it('stores null prefix when not provided', function () {
    $route = new Route(method: ['GET'], path: '/users');

    expect($route->prefix)->toBeNull();
});

it('stores name when provided', function () {
    $name = 'users.index';
    $route = new Route(method: ['GET'], path: '/users', name: $name);

    expect($route->name)->toBe($name);
});

it('stores null name when not provided', function () {
    $route = new Route(method: ['GET'], path: '/users');

    expect($route->name)->toBeNull();
});

it('handles single HTTP method', function () {
    $route = new Route(method: ['DELETE'], path: '/users/{id}');

    expect($route->method)->toHaveCount(1)
        ->and($route->method[0])->toBe('DELETE');
});

it('handles multiple HTTP methods', function () {
    $route = new Route(method: ['PUT', 'PATCH'], path: '/users/{id}');

    expect($route->method)->toHaveCount(2)
        ->and($route->method)->toBe(['PUT', 'PATCH']);
});

it('allows empty prefix explicitly', function () {
    $route = new Route(method: ['GET'], path: '/users', prefix: '');

    expect($route->prefix)->toBe('');
});

it('allows empty name explicitly', function () {
    $route = new Route(method: ['GET'], path: '/users', name: '');

    expect($route->name)->toBe('');
});

it('has public properties accessible', function () {
    $route = new Route(
        method: ['POST'],
        path: '/users',
        prefix: '/api',
        name: 'users.create',
    );

    expect($route->method)->toBeArray()
        ->and($route->path)->toBeString()
        ->and($route->prefix)->toBeString()
        ->and($route->name)->toBeString();
});

it('handles all HTTP methods', function () {
    $allMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
    $route = new Route(method: $allMethods, path: '/test');

    expect($route->method)->toBe($allMethods)
        ->and($route->method)->toHaveCount(7);
});
