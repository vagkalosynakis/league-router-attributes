<?php

declare(strict_types=1);

namespace VagKalosynakis\LeagueRouteAttributes\Attributes;

use Attribute;

/**
 * Declares an HTTP route on a controller method.
 *
 * This attribute enables declarative routing by annotating controller methods
 * with route definitions. It supports multiple HTTP methods per route and
 * optional prefix and name parameters for advanced routing configuration.
 *
 * @example
 * #[Route(['GET'], '/users')]
 * public function index(): Response
 * {
 *     // Handles GET /users
 * }
 *
 * @example
 * #[Route(['PUT', 'PATCH'], '/users/{id}', name: 'users.update')]
 * public function update(int $id): Response
 * {
 *     // Handles both PUT and PATCH /users/{id}
 * }
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    /**
     * @param array<string> $method Array of HTTP methods (GET, POST, PUT, PATCH, DELETE, HEAD, OPTIONS)
     * @param string $path Route pattern (e.g., '/users/{id:\d+}')
     * @param string|null $prefix Optional route prefix to prepend to path
     * @param string|null $name Optional route name for URL generation
     */
    public function __construct(
        public array $method,
        public string $path,
        public ?string $prefix = null,
        public ?string $name = null,
    ) {
    }
}
