<?php

declare(strict_types=1);

namespace VagKalosynakis\LeagueRouteAttributes\Attributes;

use Attribute;

/**
 * Applies middleware to a controller class or method.
 *
 * This attribute can be stacked multiple times and applied at both class and
 * method levels. Class-level middleware applies to all routes in the controller,
 * while method-level middleware applies only to the specific route.
 * Method-level middleware is applied after class-level middleware.
 *
 * @example
 * #[Middleware([AuthMiddleware::class])]
 * class UserController
 * {
 *     // All routes inherit AuthMiddleware
 * }
 *
 * @example
 * #[Route(['POST'], '/users')]
 * #[Middleware([ValidateInput::class])]
 * #[Middleware([RateLimitMiddleware::class])]
 * public function store(): Response
 * {
 *     // Has AuthMiddleware (from class), ValidateInput, and RateLimitMiddleware
 * }
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Middleware
{
    /**
     * @param array<string> $middlewareClasses Array of fully-qualified middleware class names
     */
    public function __construct(
        public array $middlewareClasses
    ) {
    }
}
