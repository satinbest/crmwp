<?php

namespace App\Support;

class Router
{
    private array $routes = [];
    private array $groupStack = [];

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    public function get(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    public function patch(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middlewares);
    }

    public function delete(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    public function options(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('OPTIONS', $path, $handler, $middlewares);
    }

    private function addRoute(string $method, string $path, array|callable $handler, array $middlewares = []): void
    {
        $prefix = '';
        $groupMiddlewares = [];

        foreach ($this->groupStack as $group) {
            if (!empty($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (!empty($group['middleware'])) {
                $groupMiddlewares = array_merge($groupMiddlewares, (array)$group['middleware']);
            }
        }

        $fullPath = rtrim($prefix, '/') . '/' . ltrim($path, '/');
        if ($fullPath !== '/') {
            $fullPath = rtrim($fullPath, '/');
        }

        $allMiddlewares = array_merge($groupMiddlewares, $middlewares);

        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'pattern' => $this->compilePattern($fullPath),
            'handler' => $handler,
            'middlewares' => $allMiddlewares,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = rtrim($request->getPath(), '/');
        if (empty($path)) {
            $path = '/';
        }

        // Handle CORS Preflight
        if ($method === 'OPTIONS') {
            return Response::json(['status' => 'ok'], 204, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN',
                'Access-Control-Allow-Credentials' => 'true',
            ]);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $path, $matches)) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }
                $request->setRouteParams($params);

                return $this->runPipeline($request, $route['middlewares'], $route['handler']);
            }
        }

        return Response::error('NOT_FOUND', 'مسیر درخواستی یافت نشد.', [], 404);
    }

    private function runPipeline(Request $request, array $middlewares, array|callable $handler): Response
    {
        $pipeline = array_reverse($middlewares);

        $coreAction = function (Request $req) use ($handler): Response {
            if (is_callable($handler)) {
                return $handler($req);
            }

            [$controllerClass, $action] = $handler;
            $controller = new $controllerClass();
            return $controller->$action($req);
        };

        $runner = array_reduce($pipeline, function ($next, $middleware) {
            return function (Request $req) use ($middleware, $next): Response {
                if (is_string($middleware)) {
                    $instance = new $middleware();
                    return $instance->handle($req, $next);
                } elseif (is_callable($middleware)) {
                    return $middleware($req, $next);
                }
                return $next($req);
            };
        }, $coreAction);

        return $runner($request);
    }

    private function compilePattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#u';
    }
}
