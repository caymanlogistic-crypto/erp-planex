<?php

namespace App\Http;

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): self
    {
        $this->routes[] = [
            'method'  => 'GET',
            'path'    => $path,
            'handler' => $handler,
        ];

        return $this;
    }

    public function post(string $path, callable $handler): self
    {
        $this->routes[] = [
            'method'  => 'POST',
            'path'    => $path,
            'handler' => $handler,
        ];

        return $this;
    }

    public function dispatch(string $method, string $uri): mixed
    {
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = $uri !== null ? $uri : '/';
        $uri = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            $params = $this->matchPath($route['path'], $uri);

            if ($params !== false) {
                return ($route['handler'])(...$params);
            }
        }

        http_response_code(404);
        header('Content-Type: text/plain');
        echo '404 Not Found';

        return null;
    }

    public function resolve(string $method, string $uri): mixed
    {
        return $this->dispatch($method, $uri);
    }

    private function matchPath(string $routePath, string $uri): array|false
    {
        if ($routePath === '/' && $uri === '/') {
            return [];
        }

        $routeSegments = $routePath === '/' ? [] : explode('/', trim($routePath, '/'));
        $uriSegments   = $uri === '/'       ? [] : explode('/', trim($uri, '/'));

        if (count($routeSegments) !== count($uriSegments)) {
            return false;
        }

        $params = [];

        foreach ($routeSegments as $i => $segment) {
            if (str_starts_with($segment, '{') && str_ends_with($segment, '}')) {
                $params[] = $uriSegments[$i];
            } elseif ($segment !== $uriSegments[$i]) {
                return false;
            }
        }

        return $params;
    }
}
