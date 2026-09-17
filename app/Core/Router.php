<?php

namespace App\Core;

/**
 * Minimal router: maps "method + path" to a [ControllerClass, 'method'] pair.
 * No framework dependency (per the tech-stack rules — plain PHP only).
 */
class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, array $handler): void
    {
        $pattern = preg_replace('#\{[a-zA-Z_]+\}#', '([^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';
        $this->routes[] = compact('method', 'path', 'pattern', 'handler');
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = parse_url($uri, PHP_URL_PATH);
        $basePath = (require dirname(__DIR__, 2) . '/config/app.php')['base_path'];
        if ($basePath !== '' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }
        $uri = '/' . ltrim($uri, '/');
        $uri = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['pattern'], $uri, $matches)) {
                if ($method === 'POST' && $this->postBodyExceededPhpLimit()) {
                    http_response_code(413);
                    echo 'That upload is larger than this server currently allows (check upload_max_filesize / post_max_size in php.ini). Try a smaller file.';
                    return;
                }
                if ($method === 'POST' && !$this->csrfValid()) {
                    http_response_code(419);
                    echo 'Your session has expired or this page was open too long. Please go back, refresh, and try again.';
                    return;
                }
                array_shift($matches);
                [$class, $action] = $route['handler'];
                $controller = new $class();
                call_user_func_array([$controller, $action], $matches);
                return;
            }
        }

        http_response_code(404);
        echo '404 — Page not found: ' . htmlspecialchars($uri);
    }

    /**
     * Every POST route is state-changing, so every POST route is checked —
     * there is no exempt list. Accepts the token either as a form field
     * (_token, from csrf_field()) or an X-CSRF-Token header (for the JSON
     * fetch() calls in the wizard/advisor/receipt-scan UIs). Requires
     * Auth::start() to have already run (see public/index.php) so
     * $_SESSION is available here regardless of which route matched.
     */
    private function csrfValid(): bool
    {
        $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $sessionToken = $_SESSION['_csrf'] ?? '';
        return $sessionToken !== '' && is_string($token) && $token !== '' && hash_equals($sessionToken, (string) $token);
    }

    /**
     * When an uploaded request body exceeds php.ini's post_max_size, PHP
     * silently empties $_POST and $_FILES (this is documented PHP
     * behavior, not a bug here) — the request still arrives, just with no
     * body data at all. Without this check that reads as a missing CSRF
     * token and gets reported as "session expired", which is wrong and
     * confusing for something like a large receipt photo. Detected by:
     * a body was actually sent (Content-Length > 0) but both superglobals
     * came back empty on a route that expects form/multipart data.
     */
    private function postBodyExceededPhpLimit(): bool
    {
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        $isFormPost = str_starts_with($contentType, 'multipart/form-data') || str_starts_with($contentType, 'application/x-www-form-urlencoded');
        return $contentLength > 0 && $isFormPost && empty($_POST) && empty($_FILES);
    }
}
