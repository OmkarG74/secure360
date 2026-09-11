<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Core Router for Secure360
 * Handles route registration, middleware chains, dynamic parameters, CORS, and controller dispatch
 */
class Router
{
    private array $routes = [];
    private array $groupPrefixes = [];
    private array $groupMiddlewares = [];

    /**
     * Register a GET route
     */
    public function get(string $uri, array|callable $action, array $middlewares = []): self
    {
        return $this->addRoute('GET', $uri, $action, $middlewares);
    }

    /**
     * Register a POST route
     */
    public function post(string $uri, array|callable $action, array $middlewares = []): self
    {
        return $this->addRoute('POST', $uri, $action, $middlewares);
    }

    /**
     * Register a PUT route
     */
    public function put(string $uri, array|callable $action, array $middlewares = []): self
    {
        return $this->addRoute('PUT', $uri, $action, $middlewares);
    }

    /**
     * Register a DELETE route
     */
    public function delete(string $uri, array|callable $action, array $middlewares = []): self
    {
        return $this->addRoute('DELETE', $uri, $action, $middlewares);
    }

    /**
     * Register an OPTIONS route (for CORS preflight)
     */
    public function options(string $uri, array|callable $action, array $middlewares = []): self
    {
        return $this->addRoute('OPTIONS', $uri, $action, $middlewares);
    }

    /**
     * Group routes with a shared URL prefix and optional middleware
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = end($this->groupPrefixes) ?: '';
        $newPrefix = $previousPrefix . '/' . trim($attributes['prefix'] ?? '', '/');
        $this->groupPrefixes[] = $newPrefix;

        $previousMiddlewares = end($this->groupMiddlewares) ?: [];
        $newMiddlewares = array_merge($previousMiddlewares, $attributes['middleware'] ?? []);
        $this->groupMiddlewares[] = $newMiddlewares;

        $callback($this);

        array_pop($this->groupPrefixes);
        array_pop($this->groupMiddlewares);
    }

    /**
     * Internal method to store routes
     */
    private function addRoute(string $method, string $uri, array|callable $action, array $middlewares = []): self
    {
        $currentPrefix = end($this->groupPrefixes) ?: '';
        $fullUri = rtrim($currentPrefix, '/') . '/' . ltrim($uri, '/');
        $fullUri = '/' . trim($fullUri, '/');
        if ($fullUri !== '/') {
            $fullUri = rtrim($fullUri, '/');
        }

        $groupMiddlewares = end($this->groupMiddlewares) ?: [];
        $mergedMiddlewares = array_merge($groupMiddlewares, $middlewares);

        $this->routes[] = [
            'method' => $method,
            'uri' => $fullUri,
            'action' => $action,
            'middlewares' => $mergedMiddlewares,
            'regex' => $this->compileRegex($fullUri),
        ];

        return $this;
    }

    /**
     * Convert URI with {param} placeholders into regular expression
     */
    private function compileRegex(string $uri): string
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $uri);
        return '#^' . $pattern . '$#';
    }

    /**
     * Apply CORS headers for API requests
     */
    private function handleCors(): void
    {
        if (!headers_sent()) {
            header("Access-Control-Allow-Origin: *");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        }
    }

    /**
     * Dispatch the current request to matching route and invoke action
     */
    public function dispatch(Request $request, Response $response): void
    {
        // Automatically attach CORS headers for API requests
        if (str_starts_with($request->getUri(), '/api/')) {
            $this->handleCors();

            // Handle preflight OPTIONS requests immediately
            if ($request->getMethod() === 'OPTIONS') {
                $response->setStatusCode(200)->sendHeaders();
                exit;
            }
        }

        $requestMethod = $request->getMethod();
        $requestUri = rtrim($request->getUri(), '/');
        if ($requestUri === '') {
            $requestUri = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestMethod) {
                continue;
            }

            if (preg_match($route['regex'], $requestUri, $matches)) {
                // Extract named parameter arguments
                $params = array_filter($matches, fn($key) => !is_numeric($key), ARRAY_FILTER_USE_KEY);

                // Execute middleware pipeline
                foreach ($route['middlewares'] as $middlewareClass) {
                    if (class_exists($middlewareClass)) {
                        $middlewareInstance = new $middlewareClass();
                        if (method_exists($middlewareInstance, 'handle')) {
                            $middlewareInstance->handle($request, $response);
                        }
                    }
                }

                // Execute action
                $this->executeAction($route['action'], $request, $response, $params);
                return;
            }
        }

        // Route not found (404)
        $this->handleNotFound($request, $response);
    }

    /**
     * Execute route controller action or callable
     */
    private function executeAction(array|callable $action, Request $request, Response $response, array $params): void
    {
        if (is_callable($action)) {
            call_user_func_array($action, [$request, $response, $params]);
            return;
        }

        if (is_array($action) && count($action) === 2) {
            [$controllerClass, $method] = $action;

            if (class_exists($controllerClass)) {
                $controller = new $controllerClass($request, $response);
                if (method_exists($controller, $method)) {
                    call_user_func_array([$controller, $method], [$request, $response, $params]);
                    return;
                }
            }
        }

        $response->setStatusCode(500)->html("<h1>500 Internal Server Error</h1><p>Invalid controller action.</p>");
    }

    /**
     * Handle 404 response
     */
    private function handleNotFound(Request $request, Response $response): void
    {
        if ($request->isJson() || str_starts_with($request->getUri(), '/api/')) {
            $response->json([
                'success' => false,
                'message' => 'API endpoint not found',
                'status_code' => 404,
            ], 404);
        } else {
            $response->setStatusCode(404)->html(
                "<!DOCTYPE html><html><head><title>404 Not Found - Secure360</title><style>body{font-family:sans-serif;text-align:center;padding:50px;background:#f8fafc;color:#1e293b;}h1{color:#0f172a;}</style></head><body><h1>404 Not Found</h1><p>The requested page was not found on Secure360.</p><a href='/'>Return to Home</a></body></html>"
            );
        }
    }
}
