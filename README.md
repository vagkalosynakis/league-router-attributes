# League Route Attributes

[![PHP Version](https://img.shields.io/badge/php-%5E8.0-blue)](https://www.php.net)
[![League Route](https://img.shields.io/badge/league%2Froute-%5E5.1-green)](https://route.thephpleague.com/)
[![License](https://img.shields.io/badge/license-MIT-brightgreen)](LICENSE)

Attribute-based routing for [league/route](https://route.thephpleague.com/). Define routes using PHP 8 attributes directly on controller methods, eliminating manual route registration boilerplate.

## Requirements

- PHP ^8.0
- league/route ^5.1
- psr/container ^1.0|^2.0

## Installation

```bash
composer require vagkalosynakis/league-router-attributes
```

## Usage

### Basic Routing

Use the `#[Route]` attribute to declare routes on controller methods:

```php
<?php

namespace App\Controllers;

use VagKalosynakis\LeagueRouteAttributes\Attributes\Route;

class UserController
{
    #[Route(['GET'], '/users')]
    public function index(): Response
    {
        // List users
    }

    #[Route(['GET'], '/users/{id:\d+}')]
    public function show(int $id): Response
    {
        // Show user
    }

    #[Route(['POST'], '/users')]
    public function store(Request $request): Response
    {
        // Create user
    }

    #[Route(['PUT', 'PATCH'], '/users/{id:\d+}')]
    public function update(int $id, Request $request): Response
    {
        // Update user - handles both PUT and PATCH
    }

    #[Route(['DELETE'], '/users/{id:\d+}')]
    public function destroy(int $id): Response
    {
        // Delete user
    }
}
```

### Route Prefixes and Names

```php
#[Route(['GET'], '/admin/users', prefix: '/api/v1', name: 'admin.users')]
public function adminUsers(): Response
{
    // Accessible at: /api/v1/admin/users
    // Named route: admin.users
}
```

### Middleware

Apply middleware at class level (inherited by all methods) or method level:

```php
<?php

namespace App\Controllers;

use VagKalosynakis\LeagueRouteAttributes\Attributes\Route;
use VagKalosynakis\LeagueRouteAttributes\Attributes\Middleware;

#[Middleware([AuthMiddleware::class])]
class AdminController
{
    #[Route(['GET'], '/dashboard')]
    public function dashboard(): Response
    {
        // Inherits AuthMiddleware from class
    }

    #[Route(['GET'], '/users')]
    #[Middleware([AdminOnlyMiddleware::class])]
    public function users(): Response
    {
        // Has both AuthMiddleware and AdminOnlyMiddleware
    }
}
```

### Excluding Middleware

Use `#[WithoutMiddleware]` to exclude inherited middleware from specific routes:

```php
<?php

namespace App\Controllers;

use VagKalosynakis\LeagueRouteAttributes\Attributes\Route;
use VagKalosynakis\LeagueRouteAttributes\Attributes\Middleware;
use VagKalosynakis\LeagueRouteAttributes\Attributes\WithoutMiddleware;

#[Middleware([AuthMiddleware::class])]
class ApiController
{
    #[Route(['GET'], '/profile')]
    public function profile(): Response
    {
        // Protected: requires AuthMiddleware
    }

    #[Route(['GET'], '/health')]
    #[WithoutMiddleware([AuthMiddleware::class])]
    public function health(): Response
    {
        // Public: AuthMiddleware excluded
    }
}
```

### Route Discovery

Register routes automatically by scanning a directory:

```php
<?php

use League\Route\Router;
use VagKalosynakis\LeagueRouteAttributes\Discovery\RouteDiscovery;

$router = new Router();
$container = /* your PSR-11 container */;

$discovery = new RouteDiscovery($router, $container);
$discovery->discoverRoutes(__DIR__ . '/src/Controllers');

// Routes are now registered with league/route
$response = $router->dispatch($request);
```

### Manual Registration

Or register specific controllers:

```php
<?php

$discovery = new RouteDiscovery($router, $container);
$discovery->registerRoutesForController(UserController::class);
$discovery->registerRoutesForController(AdminController::class);
```

## How It Works

The package uses PHP's Reflection API to scan classes for route attributes, then registers them with league/route using its standard API. All league/route features (middleware, route groups, strategies, etc.) work as expected.

### Supported HTTP Methods

`GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `HEAD`, `OPTIONS`

### Multiple HTTP Methods

A single route can handle multiple HTTP methods:

```php
#[Route(['GET', 'HEAD'], '/users')]
public function index(): Response
{
    // Responds to both GET and HEAD requests
}
```

### Route Parameters

Use league/route's parameter syntax with optional regex constraints:

```php
#[Route(['GET'], '/users/{id:\d+}')]              // Numeric ID
#[Route(['GET'], '/posts/{slug:[a-z0-9-]+}')]    // Alphanumeric slug
#[Route(['GET'], '/files/{path:.+}')]             // Catch-all path
```

## Architecture

```
VagKalosynakis\LeagueRouteAttributes\
├── Attributes/
│   ├── Route.php               # Route declaration
│   ├── Middleware.php          # Middleware application
│   └── WithoutMiddleware.php   # Middleware exclusion
└── Discovery/
    └── RouteDiscovery.php      # Route scanning and registration
```

## PSR Compliance

- PSR-1: Basic Coding Standard
- PSR-4: Autoloading
- PSR-12: Extended Coding Style
- PSR-11: Container Interface
- PSR-15: HTTP Handlers/Middleware

<details>
<summary>Testing</summary>

### Running Tests

```bash
# Install dev dependencies
composer install

# Run test suite
composer test

# Run with coverage
composer test:coverage

# Static analysis
composer phpstan

# Code style check
composer cs:check

# Fix code style
composer cs:fix
```

### Test Structure

```
tests/
├── Unit/              # Unit tests for attributes and discovery
└── Integration/       # Integration tests with league/route
```

### Requirements for Testing

- PHPUnit ^9.6
- Mockery ^1.5
- PHPStan ^1.10
- PHP CS Fixer ^3.16

</details>

## License

MIT License. See [LICENSE](LICENSE) file for details.

## Contributing

Contributions welcome. Please follow PSR-12 coding standards and ensure tests pass before submitting PRs.

## Credits

Built on top of [league/route](https://route.thephpleague.com/) by [The PHP League](https://thephpleague.com/).
