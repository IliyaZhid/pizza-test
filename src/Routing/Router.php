<?php

namespace Iliyazhid\PizzaTest\Routing;

use Iliyazhid\PizzaTest\Http\Request;
use Iliyazhid\PizzaTest\Http\Response;

class Router
{
    private array $routes = [];

    public function addRoute(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function handle(Request $request, Response $response): void
    {
        $uri = $request->getUri();
        $path = parse_url($uri, PHP_URL_PATH);
        $method = $request->getMethod();

        $matchedPath = false;

        foreach ($this->routes as $route) {
            $params = $this->matchPath($route['path'], $path);
            if ($params !== null) {
                $matchedPath = true;

                if ($route['method'] === $method) {
                    $handler = $this->createMiddlewareChain($route['handler'], $route['middleware']);
                    $handler($request, $response, ...array_values($params));
                    return;
                }
            }
        }

        if ($matchedPath) {
            $response->json(['error' => 'Method not allowed'], 405)->send();
            return;
        }

        $response->json(['error' => 'Endpoint not found'], 404)->send();
    }

    private function createMiddlewareChain(callable $handler, array $middleware): callable
    {
        $chain = $handler;
        foreach (array_reverse($middleware) as $middlewareConfig) {

            if (is_string($middlewareConfig)) {
                $middlewareInstance = new $middlewareConfig();
            }

            elseif (is_array($middlewareConfig) && count($middlewareConfig) === 2) {
                [$middlewareClass, $constructorArgs] = $middlewareConfig;
                $middlewareInstance = new $middlewareClass(...$constructorArgs);
            } else {
                throw new \InvalidArgumentException('Некорректная конфигурация middleware');
            }

            $chain = function (Request $request, Response $response, ...$params) use ($middlewareInstance, $chain) {
                $middlewareInstance($request, $response, $chain, ...$params);
            };
        }
        return $chain;
    }

    private function matchPath(string $routePath, string $requestPath): ?array
    {
        $routePattern = preg_replace('/\//', '\/', $routePath);
        $routePattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^\/]+)', $routePattern);
        $routePattern = '/^' . $routePattern . '$/';

        if (preg_match($routePattern, $requestPath, $matches)) {
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return null;
    }
}
