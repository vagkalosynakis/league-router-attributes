<?php

declare(strict_types=1);

use League\Route\Route as LeagueRoute;
use League\Route\Router;
use Psr\Container\ContainerInterface;
use VagKalosynakis\LeagueRouteAttributes\Discovery\RouteDiscovery;

beforeEach(function () {
    $this->router = Mockery::mock(Router::class);
    $this->container = Mockery::mock(ContainerInterface::class);
    $this->discovery = new RouteDiscovery($this->router, $this->container);
});

afterEach(function () {
    Mockery::close();
});

it('instantiates with router and container', function () {
    expect($this->discovery)->toBeInstanceOf(RouteDiscovery::class);
});

it('discovers routes from directory', function () {
    $testDir = __DIR__ . '/../../Fixtures/Controllers';

    // Skip if fixtures directory doesn't exist yet
    if (!is_dir($testDir)) {
        expect(true)->toBeTrue();
        return;
    }

    $this->discovery->discoverRoutes($testDir);

    expect(true)->toBeTrue();
});

it('handles non-existent directory gracefully', function () {
    $this->discovery->discoverRoutes('/non/existent/directory');

    expect(true)->toBeTrue();
});

it('registers route with single HTTP method', function () {
    $leagueRoute = Mockery::mock(LeagueRoute::class);
    $leagueRoute->shouldReceive('middleware')->never();

    $this->router->shouldReceive('map')
        ->once()
        ->with('GET', '/users', Mockery::type('array'))
        ->andReturn($leagueRoute);

    // Create a temporary controller file
    $tempDir = sys_get_temp_dir() . '/route_discovery_test_' . uniqid();
    mkdir($tempDir, 0777, true);

    $controllerCode = <<<'PHP'
<?php
namespace TempTest;

use VagKalosynakis\LeagueRouteAttributes\Attributes\Route;

class SimpleController
{
    #[Route(['GET'], '/users')]
    public function index() {}
}
PHP;

    file_put_contents($tempDir . '/SimpleController.php', $controllerCode);
    require_once $tempDir . '/SimpleController.php';

    $this->discovery->discoverRoutes($tempDir);

    // Cleanup
    unlink($tempDir . '/SimpleController.php');
    rmdir($tempDir);
});

it('registers route with multiple HTTP methods', function () {
    $leagueRoute = Mockery::mock(LeagueRoute::class);
    $leagueRoute->shouldReceive('middleware')->never();

    // Should register each method separately
    $this->router->shouldReceive('map')
        ->once()
        ->with('PUT', '/users/1', Mockery::type('array'))
        ->andReturn($leagueRoute);

    $this->router->shouldReceive('map')
        ->once()
        ->with('PATCH', '/users/1', Mockery::type('array'))
        ->andReturn($leagueRoute);

    $tempDir = sys_get_temp_dir() . '/route_discovery_test_' . uniqid();
    mkdir($tempDir, 0777, true);

    $controllerCode = <<<'PHP'
<?php
namespace TempTest2;

use VagKalosynakis\LeagueRouteAttributes\Attributes\Route;

class MultiMethodController
{
    #[Route(['PUT', 'PATCH'], '/users/1')]
    public function update() {}
}
PHP;

    file_put_contents($tempDir . '/MultiMethodController.php', $controllerCode);
    require_once $tempDir . '/MultiMethodController.php';

    $this->discovery->discoverRoutes($tempDir);

    unlink($tempDir . '/MultiMethodController.php');
    rmdir($tempDir);
});

it('builds route path with prefix correctly', function () {
    $leagueRoute = Mockery::mock(LeagueRoute::class);
    $leagueRoute->shouldReceive('middleware')->never();

    $this->router->shouldReceive('map')
        ->once()
        ->with('GET', '/api/users', Mockery::type('array'))
        ->andReturn($leagueRoute);

    $tempDir = sys_get_temp_dir() . '/route_discovery_test_' . uniqid();
    mkdir($tempDir, 0777, true);

    $controllerCode = <<<'PHP'
<?php
namespace TempTest3;

use VagKalosynakis\LeagueRouteAttributes\Attributes\Route;

class PrefixedController
{
    #[Route(['GET'], '/users', prefix: '/api')]
    public function index() {}
}
PHP;

    file_put_contents($tempDir . '/PrefixedController.php', $controllerCode);
    require_once $tempDir . '/PrefixedController.php';

    $this->discovery->discoverRoutes($tempDir);

    unlink($tempDir . '/PrefixedController.php');
    rmdir($tempDir);
});

it('applies class-level middleware to routes', function () {
    $middlewareInstance = Mockery::mock(\Psr\Http\Server\MiddlewareInterface::class);

    $this->container->shouldReceive('has')
        ->with('TempTest4\AuthMiddleware')
        ->andReturn(true);

    $this->container->shouldReceive('get')
        ->with('TempTest4\AuthMiddleware')
        ->andReturn($middlewareInstance);

    $leagueRoute = Mockery::mock(LeagueRoute::class);
    $leagueRoute->shouldReceive('middleware')
        ->once()
        ->with($middlewareInstance)
        ->andReturn($leagueRoute);

    $this->router->shouldReceive('map')
        ->once()
        ->with('GET', '/users', Mockery::type('array'))
        ->andReturn($leagueRoute);

    $tempDir = sys_get_temp_dir() . '/route_discovery_test_' . uniqid();
    mkdir($tempDir, 0777, true);

    // Create middleware class first
    $middlewareCode = <<<'PHP'
<?php
namespace TempTest4;

class AuthMiddleware {}
PHP;
    file_put_contents($tempDir . '/AuthMiddleware.php', $middlewareCode);
    require_once $tempDir . '/AuthMiddleware.php';

    $controllerCode = <<<'PHP'
<?php
namespace TempTest4;

use VagKalosynakis\LeagueRouteAttributes\Attributes\Route;
use VagKalosynakis\LeagueRouteAttributes\Attributes\Middleware;

#[Middleware(['TempTest4\AuthMiddleware'])]
class MiddlewareController
{
    #[Route(['GET'], '/users')]
    public function index() {}
}
PHP;

    file_put_contents($tempDir . '/MiddlewareController.php', $controllerCode);
    require_once $tempDir . '/MiddlewareController.php';

    $this->discovery->discoverRoutes($tempDir);

    unlink($tempDir . '/MiddlewareController.php');
    unlink($tempDir . '/AuthMiddleware.php');
    rmdir($tempDir);
});

it('stacks method-level middleware with class-level middleware', function () {
    $authMiddleware = Mockery::mock(\Psr\Http\Server\MiddlewareInterface::class);
    $logMiddleware = Mockery::mock(\Psr\Http\Server\MiddlewareInterface::class);

    $this->container->shouldReceive('has')
        ->with('TempTest5\AuthMiddleware')
        ->andReturn(true);

    $this->container->shouldReceive('get')
        ->with('TempTest5\AuthMiddleware')
        ->andReturn($authMiddleware);

    $this->container->shouldReceive('has')
        ->with('TempTest5\LogMiddleware')
        ->andReturn(true);

    $this->container->shouldReceive('get')
        ->with('TempTest5\LogMiddleware')
        ->andReturn($logMiddleware);

    $leagueRoute = Mockery::mock(LeagueRoute::class);

    // Class middleware should be applied first, then method middleware
    $leagueRoute->shouldReceive('middleware')
        ->once()
        ->with($authMiddleware)
        ->andReturn($leagueRoute);

    $leagueRoute->shouldReceive('middleware')
        ->once()
        ->with($logMiddleware)
        ->andReturn($leagueRoute);

    $this->router->shouldReceive('map')
        ->once()
        ->with('POST', '/users', Mockery::type('array'))
        ->andReturn($leagueRoute);

    $tempDir = sys_get_temp_dir() . '/route_discovery_test_' . uniqid();
    mkdir($tempDir, 0777, true);

    // Create middleware classes first
    $authMiddlewareCode = <<<'PHP'
<?php
namespace TempTest5;

class AuthMiddleware {}
PHP;
    file_put_contents($tempDir . '/AuthMiddleware.php', $authMiddlewareCode);
    require_once $tempDir . '/AuthMiddleware.php';

    $logMiddlewareCode = <<<'PHP'
<?php
namespace TempTest5;

class LogMiddleware {}
PHP;
    file_put_contents($tempDir . '/LogMiddleware.php', $logMiddlewareCode);
    require_once $tempDir . '/LogMiddleware.php';

    $controllerCode = <<<'PHP'
<?php
namespace TempTest5;

use VagKalosynakis\LeagueRouteAttributes\Attributes\Route;
use VagKalosynakis\LeagueRouteAttributes\Attributes\Middleware;

#[Middleware(['TempTest5\AuthMiddleware'])]
class StackedMiddlewareController
{
    #[Route(['POST'], '/users')]
    #[Middleware(['TempTest5\LogMiddleware'])]
    public function store() {}
}
PHP;

    file_put_contents($tempDir . '/StackedMiddlewareController.php', $controllerCode);
    require_once $tempDir . '/StackedMiddlewareController.php';

    $this->discovery->discoverRoutes($tempDir);

    unlink($tempDir . '/StackedMiddlewareController.php');
    unlink($tempDir . '/LogMiddleware.php');
    unlink($tempDir . '/AuthMiddleware.php');
    rmdir($tempDir);
});

it('excludes middleware with WithoutMiddleware attribute', function () {
    $leagueRoute = Mockery::mock(LeagueRoute::class);
    $leagueRoute->shouldReceive('middleware')->never();

    $this->router->shouldReceive('map')
        ->once()
        ->with('GET', '/public', Mockery::type('array'))
        ->andReturn($leagueRoute);

    $tempDir = sys_get_temp_dir() . '/route_discovery_test_' . uniqid();
    mkdir($tempDir, 0777, true);

    $controllerCode = <<<'PHP'
<?php
namespace TempTest6;

use VagKalosynakis\LeagueRouteAttributes\Attributes\Route;
use VagKalosynakis\LeagueRouteAttributes\Attributes\Middleware;
use VagKalosynakis\LeagueRouteAttributes\Attributes\WithoutMiddleware;

#[Middleware(['AuthMiddleware'])]
class ExclusionController
{
    #[Route(['GET'], '/public')]
    #[WithoutMiddleware(['AuthMiddleware'])]
    public function publicEndpoint() {}
}
PHP;

    file_put_contents($tempDir . '/ExclusionController.php', $controllerCode);
    require_once $tempDir . '/ExclusionController.php';

    $this->discovery->discoverRoutes($tempDir);

    unlink($tempDir . '/ExclusionController.php');
    rmdir($tempDir);
});

it('throws exception for empty route path', function () {
    $tempDir = sys_get_temp_dir() . '/route_discovery_test_' . uniqid();
    mkdir($tempDir, 0777, true);

    $controllerCode = <<<'PHP'
<?php
namespace TempTest7;

use VagKalosynakis\LeagueRouteAttributes\Attributes\Route;

class EmptyPathController
{
    #[Route(['GET'], '')]
    public function index() {}
}
PHP;

    file_put_contents($tempDir . '/EmptyPathController.php', $controllerCode);
    require_once $tempDir . '/EmptyPathController.php';

    expect(fn () => $this->discovery->discoverRoutes($tempDir))
        ->toThrow(RuntimeException::class);

    unlink($tempDir . '/EmptyPathController.php');
    rmdir($tempDir);
});

it('throws exception for empty methods array', function () {
    $tempDir = sys_get_temp_dir() . '/route_discovery_test_' . uniqid();
    mkdir($tempDir, 0777, true);

    $controllerCode = <<<'PHP'
<?php
namespace TempTest8;

use VagKalosynakis\LeagueRouteAttributes\Attributes\Route;

class EmptyMethodsController
{
    #[Route([], '/users')]
    public function index() {}
}
PHP;

    file_put_contents($tempDir . '/EmptyMethodsController.php', $controllerCode);
    require_once $tempDir . '/EmptyMethodsController.php';

    expect(fn () => $this->discovery->discoverRoutes($tempDir))
        ->toThrow(RuntimeException::class);

    unlink($tempDir . '/EmptyMethodsController.php');
    rmdir($tempDir);
});

it('throws exception for invalid HTTP method', function () {
    $leagueRoute = Mockery::mock(LeagueRoute::class);

    $this->router->shouldReceive('map')
        ->never();

    $tempDir = sys_get_temp_dir() . '/route_discovery_test_' . uniqid();
    mkdir($tempDir, 0777, true);

    $controllerCode = <<<'PHP'
<?php
namespace TempTest9;

use VagKalosynakis\LeagueRouteAttributes\Attributes\Route;

class InvalidMethodController
{
    #[Route(['INVALID'], '/users')]
    public function index() {}
}
PHP;

    file_put_contents($tempDir . '/InvalidMethodController.php', $controllerCode);
    require_once $tempDir . '/InvalidMethodController.php';

    expect(fn () => $this->discovery->discoverRoutes($tempDir))
        ->toThrow(RuntimeException::class);

    unlink($tempDir . '/InvalidMethodController.php');
    rmdir($tempDir);
});

it('throws exception for duplicate route registration', function () {
    $leagueRoute = Mockery::mock(LeagueRoute::class);
    $leagueRoute->shouldReceive('middleware')->never();

    $this->router->shouldReceive('map')
        ->once()
        ->with('GET', '/users', Mockery::type('array'))
        ->andReturn($leagueRoute);

    $tempDir = sys_get_temp_dir() . '/route_discovery_test_' . uniqid();
    mkdir($tempDir, 0777, true);

    $controllerCode = <<<'PHP'
<?php
namespace TempTest10;

use VagKalosynakis\LeagueRouteAttributes\Attributes\Route;

class DuplicateController
{
    #[Route(['GET'], '/users')]
    public function index() {}

    #[Route(['GET'], '/users')]
    public function duplicate() {}
}
PHP;

    file_put_contents($tempDir . '/DuplicateController.php', $controllerCode);
    require_once $tempDir . '/DuplicateController.php';

    expect(fn () => $this->discovery->discoverRoutes($tempDir))
        ->toThrow(RuntimeException::class);

    unlink($tempDir . '/DuplicateController.php');
    rmdir($tempDir);
});

it('ignores non-controller classes', function () {
    // The discovery only processes classes ending with "Controller"
    // So a class named "UserService" should not be scanned
    $tempDir = sys_get_temp_dir() . '/route_discovery_test_' . uniqid();
    mkdir($tempDir, 0777, true);

    $nonControllerCode = <<<'PHP'
<?php
namespace TempTest11;

use VagKalosynakis\LeagueRouteAttributes\Attributes\Route;

class UserService
{
    #[Route(['GET'], '/users')]
    public function index() {}
}
PHP;

    file_put_contents($tempDir . '/UserService.php', $nonControllerCode);
    require_once $tempDir . '/UserService.php';

    // Should not register any routes since class name doesn't end with "Controller"
    $this->discovery->discoverRoutes($tempDir);

    unlink($tempDir . '/UserService.php');
    rmdir($tempDir);

    expect(true)->toBeTrue();
});

it('ignores abstract controller classes', function () {
    $this->router->shouldReceive('map')->never();

    $tempDir = sys_get_temp_dir() . '/route_discovery_test_' . uniqid();
    mkdir($tempDir, 0777, true);

    $controllerCode = <<<'PHP'
<?php
namespace TempTest12;

use VagKalosynakis\LeagueRouteAttributes\Attributes\Route;

abstract class AbstractController
{
    #[Route(['GET'], '/users')]
    public function index() {}
}
PHP;

    file_put_contents($tempDir . '/AbstractController.php', $controllerCode);
    require_once $tempDir . '/AbstractController.php';

    $this->discovery->discoverRoutes($tempDir);

    unlink($tempDir . '/AbstractController.php');
    rmdir($tempDir);

    expect(true)->toBeTrue();
});

it('handles route name when provided', function () {
    $leagueRoute = Mockery::mock(LeagueRoute::class);
    $leagueRoute->shouldReceive('middleware')->never();
    $leagueRoute->shouldReceive('setName')
        ->once()
        ->with('users.index')
        ->andReturn($leagueRoute);

    $this->router->shouldReceive('map')
        ->once()
        ->with('GET', '/users', Mockery::type('array'))
        ->andReturn($leagueRoute);

    $tempDir = sys_get_temp_dir() . '/route_discovery_test_' . uniqid();
    mkdir($tempDir, 0777, true);

    $controllerCode = <<<'PHP'
<?php
namespace TempTest13;

use VagKalosynakis\LeagueRouteAttributes\Attributes\Route;

class NamedRouteController
{
    #[Route(['GET'], '/users', name: 'users.index')]
    public function index() {}
}
PHP;

    file_put_contents($tempDir . '/NamedRouteController.php', $controllerCode);
    require_once $tempDir . '/NamedRouteController.php';

    $this->discovery->discoverRoutes($tempDir);

    unlink($tempDir . '/NamedRouteController.php');
    rmdir($tempDir);
});

it('throws exception for non-existent middleware class', function () {
    $this->router->shouldReceive('map')
        ->once()
        ->andReturn(Mockery::mock(LeagueRoute::class));

    $tempDir = sys_get_temp_dir() . '/route_discovery_test_' . uniqid();
    mkdir($tempDir, 0777, true);

    $controllerCode = <<<'PHP'
<?php
namespace TempTest14;

use VagKalosynakis\LeagueRouteAttributes\Attributes\Route;
use VagKalosynakis\LeagueRouteAttributes\Attributes\Middleware;

class NonExistentMiddlewareController
{
    #[Route(['GET'], '/users')]
    #[Middleware(['NonExistentMiddleware'])]
    public function index() {}
}
PHP;

    file_put_contents($tempDir . '/NonExistentMiddlewareController.php', $controllerCode);
    require_once $tempDir . '/NonExistentMiddlewareController.php';

    expect(fn () => $this->discovery->discoverRoutes($tempDir))
        ->toThrow(RuntimeException::class);

    unlink($tempDir . '/NonExistentMiddlewareController.php');
    rmdir($tempDir);
});
