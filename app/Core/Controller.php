<?php

namespace App\Core;

class Controller
{
    public function __construct()
    {
        // Individual controllers opt into auth via requireAuth()/requireAdmin()
        // in their own constructors — the base stays open so AuthController
        // (login/logout) never triggers a redirect loop.
    }

    protected function requireAuth(): void
    {
        \App\Core\Auth::require();
    }

    protected function requireAdmin(): void
    {
        \App\Core\Auth::requireAdmin();
    }

    /** The logged-in user's id — every user has their own separate tracker, so this must scope every financial-data query a controller makes. Only call after requireAuth()/requireAdmin(). */
    protected function currentUserId(): int
    {
        return (int) (\App\Core\Auth::user()['id'] ?? 0);
    }

    protected function view(string $view, array $data = []): void
    {
        extract($data);
        $viewFile = dirname(__DIR__) . '/Views/' . $view . '.php';
        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View not found: $view");
        }
        require dirname(__DIR__) . '/Views/layouts/header.php';
        require $viewFile;
        require dirname(__DIR__) . '/Views/layouts/footer.php';
    }

    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function redirect(string $path): void
    {
        $base = (require dirname(__DIR__, 2) . '/config/app.php')['base_path'];
        header('Location: ' . $base . $path);
        exit;
    }

    protected function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }
}
