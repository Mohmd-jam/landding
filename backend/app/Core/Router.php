<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

/**
 * Explicit route table.
 *
 * Routes are declared in backend/routes/*.php with their middleware attached,
 * so `php -l` and a quick read of the route files describe the whole surface of
 * the API — no hidden magic, no attribute scanning.
 */
final class Router
{
    /** @var array<int,array<string,mixed>> */
    private array $routes = [];

    private string $groupPrefix = '';
    private array $groupMiddleware = [];
    private ?string $lastName = null;

    /** @var array<string,string> */
    private array $named = [];

    public function get(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add(['GET'], $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add(['POST'], $path, $handler, $middleware);
    }

    public function put(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add(['PUT'], $path, $handler, $middleware);
    }

    public function patch(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add(['PATCH'], $path, $handler, $middleware);
    }

    public function delete(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add(['DELETE'], $path, $handler, $middleware);
    }

    public function options(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add(['OPTIONS'], $path, $handler, $middleware);
    }

    public function any(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $path, $handler, $middleware);
    }

    /**
     * Classic REST resource.
     *
     * @param array<int,string> $only  keep only these actions
     * @param array<int,string> $except drop these actions
     */
    public function resource(string $path, string $controller, array $middleware = [], array $only = [], array $except = []): self
    {
        $actions = [
            'index' => ['GET', ''],
            'show' => ['GET', '/{id}'],
            'store' => ['POST', ''],
            'update' => ['PUT', '/{id}'],
            'destroy' => ['DELETE', '/{id}'],
        ];

        foreach ($actions as $action => [$method, $suffix]) {
            if (($only !== [] && !in_array($action, $only, true)) || in_array($action, $except, true)) {
                continue;
            }

            $this->add([$method], $path . $suffix, [$controller, $action], $middleware);
        }

        return $this;
    }

    /** @param array<int,string> $middleware */
    public function group(string $prefix, array $middleware, Closure $callback): self
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix = rtrim($previousPrefix . '/' . trim($prefix, '/'), '/');
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;

        return $this;
    }

    /** Name the route registered last so links can be generated. */
    public function name(string $name): self
    {
        if ($this->lastName !== null && isset($this->routes[array_key_last($this->routes)])) {
            $index = array_key_last($this->routes);
            $this->routes[$index]['name'] = $name;
            $this->named[$name] = $this->routes[$index]['path'];
        }

        return $this;
    }

    /** @param array<string,mixed> $params */
    public function url(string $name, array $params = []): string
    {
        $path = $this->named[$name] ?? '/';

        foreach ($params as $key => $value) {
            $path = preg_replace('/\{' . preg_quote((string) $key, '/') . '(\?)?(:[^}]+)?\}/', rawurlencode((string) $value), $path, 1) ?? $path;
        }

        return $path;
    }

    /** @param array<int,string> $methods */
    public function add(array $methods, string $path, mixed $handler, array $middleware = []): self
    {
        $path = '/' . trim($this->groupPrefix . '/' . trim($path, '/'), '/');

        if ($path === '//' || $path === '') {
            $path = '/';
        }

        $this->routes[] = [
            'methods' => array_map('strtoupper', $methods),
            'path' => $path,
            'pattern' => $this->compile($path),
            'handler' => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
            'name' => null,
        ];

        return $this;
    }

    /**
     * Find the route for a request.
     *
     * @return array{route:array<string,mixed>,params:array<string,string>}|null
     */
    public function match(Request $request): ?array
    {
        $path = $request->path();
        $method = $request->method();

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $path, $matches) !== 1) {
                continue;
            }

            if (!in_array($method, $route['methods'], true)) {
                continue;
            }

            $params = [];

            foreach ($matches as $key => $value) {
                if (!is_int($key) && $value !== '') {
                    $params[$key] = $value;
                }
            }

            return ['route' => $route, 'params' => $params];
        }

        return null;
    }

    /** True when the path exists with another HTTP verb (used for 405). */
    public function pathExists(string $path): bool
    {
        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $path) === 1) {
                return true;
            }
        }

        return false;
    }

    /** Run the request through its route middleware and handler. */
    public function dispatch(Request $request): Response
    {
        $match = $this->match($request);

        if ($match === null) {
            if ($this->pathExists($request->path())) {
                throw new HttpException(405, 'This URL does not accept ' . $request->method() . ' requests.');
            }

            throw HttpException::notFound('No route matches ' . $request->path());
        }

        $request->setRouteParams($match['params']);

        $pipeline = array_reduce(
            array_reverse($match['route']['middleware']),
            fn (Closure $next, mixed $layer): Closure => function (Request $request) use ($next, $layer): Response {
                $middleware = $this->resolveMiddleware($layer);

                return $middleware->handle($request, $next);
            },
            fn (Request $request): Response => $this->callAction($match['route']['handler'], $request)
        );

        return $pipeline($request);
    }

    private function callAction(mixed $handler, Request $request): Response
    {
        if ($handler instanceof Closure) {
            $result = $handler($request);
        } elseif (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            $result = (new $class())->{$method}($request);
        } elseif (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $result = (new $class())->{$method}($request);
        } else {
            throw HttpException::server('Unsupported route handler.');
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result) || is_object($result)) {
            return Response::json($result);
        }

        return Response::html((string) $result);
    }

    private function resolveMiddleware(mixed $layer): object
    {
        if (is_object($layer)) {
            return $layer;
        }

        $name = (string) $layer;

        // Middleware may carry parameters: throttle:contact, role:super_admin
        [$base, $parameter] = array_pad(explode(':', $name, 2), 2, null);

        $class = str_contains($base, '\\')
            ? $base
            : 'App\\Http\\Middleware\\' . ucfirst(Str::studly($base));

        if (!class_exists($class)) {
            throw HttpException::server('Unknown middleware: ' . $name);
        }

        return $parameter !== null ? new $class($parameter) : new $class();
    }

    /** Convert a route path into a named-group regular expression. */
    private function compile(string $path): string
    {
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(\?)?(?::([^}]+))?\}/',
            static function (array $matches): string {
                $name = $matches[1];
                $optional = ($matches[2] ?? '') === '?';
                $constraint = $matches[3] ?? '[^/]+';

                return '(' . ($optional ? '?:' : '') . '(?P<' . $name . '>' . $constraint . '))' . ($optional ? '?' : '');
            },
            $path
        ) ?? $path;

        return '#^' . $pattern . '$#u';
    }

    /** @return array<int,array<string,mixed>> */
    public function routes(): array
    {
        return $this->routes;
    }
}
