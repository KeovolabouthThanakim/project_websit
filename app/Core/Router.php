<?php
namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        // "/users/{id}" -> regex có tham số tên "id"
        $pattern = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $path);
        $this->routes[] = [
            'method'     => $method,
            'regex'      => '#^' . $pattern . '$#',
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $method) {
                continue;
            }

            // Mọi request POST đều phải có CSRF token hợp lệ
            if ($method === 'POST' && !Session::verifyCsrf($request->input('_csrf'))) {
                Response::abort(419, 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang rồi thử lại.');
            }

            Middleware::run($route['middleware']);

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            [$class, $action] = $route['handler'];
            (new $class($request))->$action(...array_values($params));
            return;
        }

        Response::abort($pathMatched ? 405 : 404);
    }
}
