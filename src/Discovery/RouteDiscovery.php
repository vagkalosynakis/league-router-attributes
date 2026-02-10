<?php

declare(strict_types=1);

namespace VagKalosynakis\LeagueRouteAttributes\Discovery;

use League\Route\Router;
use Psr\Container\ContainerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use VagKalosynakis\LeagueRouteAttributes\Attributes\Middleware;
use VagKalosynakis\LeagueRouteAttributes\Attributes\Route;
use VagKalosynakis\LeagueRouteAttributes\Attributes\WithoutMiddleware;

class RouteDiscovery
{
    /** @var array<string, true> */
    private array $registered = [];

    /** @var array<string, true> */
    private array $allowedHttpMethods = [
        'GET' => true,
        'POST' => true,
        'PUT' => true,
        'PATCH' => true,
        'DELETE' => true,
        'HEAD' => true,
        'OPTIONS' => true,
    ];

    public function __construct(
        private Router $router,
        private ContainerInterface $container
    ) {}

    public function discoverRoutes(string $directory): void
    {
        foreach ($this->discoverControllersInDirectory($directory) as $controllerClass) {
            $this->registerRoutesForController($controllerClass);
        }
    }

    /**
     * @return array<string>
     */
    private function discoverControllersInDirectory(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $controllers = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $className = $this->extractClassNameFromFile($file->getPathname());
            if ($className === null) {
                continue;
            }

            // Only "*Controller" classes
            if (!str_ends_with($className, 'Controller')) {
                continue;
            }

            // Autoloader is bootstrapped per your setup; fail fast if mismatch.
            if (!class_exists($className)) {
                throw new RuntimeException(
                    "Controller class '{$className}' was found in '{$file->getPathname()}' but is not autoloadable."
                );
            }

            $reflection = new ReflectionClass($className);

            if ($reflection->isAbstract() || $reflection->isInterface()) {
                continue;
            }

            $controllers[] = $className;
        }

        return $controllers;
    }

    /**
     * Extract the fully qualified class name from a PHP file.
     * Returns the first non-anonymous class found.
     */
    private function extractClassNameFromFile(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            return null;
        }

        $tokens = token_get_all($contents);

        $namespace = '';
        $className = null;

        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i])) {
                continue;
            }

            if ($tokens[$i][0] === T_NAMESPACE) {
                $namespace = '';

                for ($j = $i + 1; $j < $count; $j++) {
                    if (!is_array($tokens[$j])) {
                        break;
                    }

                    if (in_array($tokens[$j][0], [T_STRING, T_NS_SEPARATOR], true)) {
                        $namespace .= $tokens[$j][1];
                        continue;
                    }

                    // skip whitespace
                    if ($tokens[$j][0] === T_WHITESPACE) {
                        continue;
                    }

                    // stop when namespace statement ends
                    break;
                }
            }

            if ($tokens[$i][0] === T_CLASS) {
                // Skip anonymous classes: "new class (...)"
                $prev = $i - 1;
                while ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_WHITESPACE) {
                    $prev--;
                }
                if ($prev >= 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_NEW) {
                    continue;
                }

                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        $className = $tokens[$j][1];
                        break 2;
                    }
                }
            }
        }

        if ($className === null) {
            return null;
        }

        return $namespace !== '' ? $namespace . '\\' . $className : $className;
    }

    private function registerRoutesForController(string $controllerClass): void
    {
        $reflectionClass = new ReflectionClass($controllerClass);

        $classMiddleware = $this->getMiddlewareFromAttributes(
            $reflectionClass->getAttributes(Middleware::class)
        );

        $classExcluded = $this->getExcludedMiddlewareFromAttributes(
            $reflectionClass->getAttributes(WithoutMiddleware::class)
        );

        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Only methods declared in the concrete controller class (no inherited)
            if ($method->getDeclaringClass()->getName() !== $controllerClass) {
                continue;
            }

            $routeAttributes = $method->getAttributes(Route::class);
            $routeAttrCount = count($routeAttributes);

            if ($routeAttrCount === 0) {
                continue;
            }

            if ($routeAttrCount > 1) {
                throw new RuntimeException(sprintf(
                    "Multiple #[Route] attributes are not allowed. Found %d on %s::%s().",
                    $routeAttrCount,
                    $controllerClass,
                    $method->getName()
                ));
            }

            /** @var Route $route */
            $route = $routeAttributes[0]->newInstance();

            $this->validateRouteAttribute($route, $controllerClass, $method->getName());

            $finalPath = $this->buildRoutePath($route->prefix, $route->path);

            $methodMiddleware = $this->getMiddlewareFromAttributes(
                $method->getAttributes(Middleware::class)
            );

            $methodExcluded = $this->getExcludedMiddlewareFromAttributes(
                $method->getAttributes(WithoutMiddleware::class)
            );

            // Build final middleware:
            // - order: class first, then method
            // - exclusions: class exclusions apply to all methods; method exclusions apply only to this method
            // - exclusions apply to the FINAL combined stack (so method exclusions can remove class middleware too)
            // - dedupe
            $combinedMiddleware = array_merge($classMiddleware, $methodMiddleware);
            $combinedExcluded = array_merge($classExcluded, $methodExcluded);
            $finalMiddleware = $this->removeExcludedAndDedupe($combinedMiddleware, $combinedExcluded);

            foreach ($route->method as $httpMethodRaw) {
                $httpMethod = strtoupper(trim($httpMethodRaw));

                if (!isset($this->allowedHttpMethods[$httpMethod])) {
                    throw new RuntimeException(sprintf(
                        "Invalid HTTP method '%s' on %s::%s(). Allowed: %s",
                        $httpMethodRaw,
                        $controllerClass,
                        $method->getName(),
                        implode(', ', array_keys($this->allowedHttpMethods))
                    ));
                }

                $conflictKey = $httpMethod . ' ' . $finalPath;
                if (isset($this->registered[$conflictKey])) {
                    throw new RuntimeException(sprintf(
                        "Route conflict: '%s' is already registered (attempted again in %s::%s()).",
                        $conflictKey,
                        $controllerClass,
                        $method->getName()
                    ));
                }
                $this->registered[$conflictKey] = true;

                // Your preferred "static-style" descriptor
                $handler = [$controllerClass, $method->getName()];

                $leagueRoute = $this->router->map($httpMethod, $finalPath, $handler);

                if ($route->name !== null && $route->name !== '') {
                    if (!method_exists($leagueRoute, 'setName')) {
                        throw new RuntimeException("Route naming requested but route object does not support setName().");
                    }
                    $leagueRoute->setName($route->name);
                }

                // boot-time middleware instantiation (your requirement)
                foreach ($finalMiddleware as $middlewareClass) {
                    $this->assertValidMiddleware($middlewareClass, $controllerClass, $method->getName(), $conflictKey);
                    $leagueRoute->middleware($this->container->get($middlewareClass));
                }
            }
        }
    }

    private function validateRouteAttribute(Route $route, string $controllerClass, string $methodName): void
    {
        if (trim($route->path) === '') {
            throw new RuntimeException("Empty route path on {$controllerClass}::{$methodName}().");
        }

        if ($route->method === []) {
            throw new RuntimeException("Route methods cannot be empty on {$controllerClass}::{$methodName}().");
        }

        if ($route->prefix !== null && trim($route->prefix) === '') {
            // normalize empty-to-null
            $route->prefix = null;
        }
    }

    private function assertValidMiddleware(string $middlewareClass, string $controllerClass, string $methodName, string $routeKey): void
    {
        if (trim($middlewareClass) === '') {
            throw new RuntimeException("Empty middleware class on {$controllerClass}::{$methodName}() for {$routeKey}.");
        }

        if (!class_exists($middlewareClass)) {
            throw new RuntimeException(
                "Middleware class '{$middlewareClass}' does not exist (referenced by {$controllerClass}::{$methodName}() for {$routeKey})."
            );
        }

        // Prefer has() if available; otherwise let get() throw.
        if (method_exists($this->container, 'has') && !$this->container->has($middlewareClass)) {
            throw new RuntimeException(
                "Container has no entry for middleware '{$middlewareClass}' (referenced by {$controllerClass}::{$methodName}() for {$routeKey})."
            );
        }
    }

    /**
     * @param array $middlewareAttributes
     * @return array<string>
     */
    private function getMiddlewareFromAttributes(array $middlewareAttributes): array
    {
        $middleware = [];

        foreach ($middlewareAttributes as $attribute) {
            /** @var Middleware $middlewareAttr */
            $middlewareAttr = $attribute->newInstance();

            foreach ($middlewareAttr->middlewareClasses as $m) {
                $middleware[] = (string) $m;
            }
        }

        return $middleware;
    }

    /**
     * @param array $withoutMiddlewareAttributes
     * @return array<string>
     */
    private function getExcludedMiddlewareFromAttributes(array $withoutMiddlewareAttributes): array
    {
        $excluded = [];

        foreach ($withoutMiddlewareAttributes as $attribute) {
            /** @var WithoutMiddleware $withoutMiddlewareAttr */
            $withoutMiddlewareAttr = $attribute->newInstance();

            foreach ($withoutMiddlewareAttr->middlewareClasses as $m) {
                $excluded[] = (string) $m;
            }
        }

        return $excluded;
    }

    private function buildRoutePath(?string $prefix, string $routePath): string
    {
        $prefix = trim((string)($prefix ?? ''), '/');
        $routePath = trim($routePath, '/');

        if ($prefix === '' && $routePath === '') {
            return '/';
        }

        if ($prefix === '') {
            return '/' . $routePath;
        }

        if ($routePath === '') {
            return '/' . $prefix;
        }

        return '/' . $prefix . '/' . $routePath;
    }

    /**
     * Removes excluded middleware and de-duplicates while preserving order.
     *
     * @param array<string> $middleware
     * @param array<string> $excluded
     * @return array<string>
     */
    private function removeExcludedAndDedupe(array $middleware, array $excluded): array
    {
        // 1) remove excluded (preserves order)
        $filtered = array_values(array_diff($middleware, $excluded));

        // 2) dedupe (keeps first occurrence order)
        return array_values(array_unique($filtered));
    }
}
