<?php

declare(strict_types=1);

namespace VagKalosynakis\LeagueRouteAttributes\Attributes;

use Attribute;

/**
 * Excludes specific middleware from a controller class or method.
 *
 * This attribute provides fine-grained control over middleware inheritance,
 * allowing specific routes to exclude middleware that would otherwise be
 * inherited from the class level. Useful for creating public routes in
 * otherwise protected controllers.
 *
 * @example
 * #[Middleware([AuthMiddleware::class])]
 * class UserController
 * {
 *     #[Route(['GET'], '/users/public')]
 *     #[WithoutMiddleware([AuthMiddleware::class])]
 *     public function publicList(): Response
 *     {
 *         // AuthMiddleware is excluded, route is public
 *     }
 * }
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class WithoutMiddleware
{
    /**
     * @param array<string> $middlewareClasses Array of fully-qualified middleware class names to exclude
     */
    public function __construct(
        public array $middlewareClasses
    ) {
    }
}
