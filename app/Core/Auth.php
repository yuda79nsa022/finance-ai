<?php

namespace App\Core;

use App\Models\User;

/**
 * Thin session-based auth wrapper. Call Auth::start() once per request
 * (the front controller does this before routing), then Auth::require()
 * at the top of any controller action that needs a logged-in user, and
 * Auth::requireAdmin() for admin-only actions (Settings, backup/restore,
 * category/lender/financial-year management).
 */
class Auth
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function attempt(string $email, string $password): bool
    {
        $user = User::findByEmail($email);
        if (!$user || !$user['is_active'] || !User::verifyPassword($user, $password)) {
            return false;
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function isAdmin(): bool
    {
        return self::check() && ($_SESSION['user_role'] ?? '') === 'admin';
    }

    public static function user(): ?array
    {
        return self::check() ? [
            'id'   => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'role' => $_SESSION['user_role'],
        ] : null;
    }

    /** Call at the top of any controller action that requires login. Redirects to /login if not authenticated. */
    public static function require(): void
    {
        self::start();
        if (!self::check()) {
            $base = (require dirname(__DIR__, 2) . '/config/app.php')['base_path'];
            $return = urlencode($_SERVER['REQUEST_URI'] ?? '/');
            header('Location: ' . $base . '/login?return=' . $return);
            exit;
        }
    }

    /** Call at the top of any controller action that requires admin. Redirects home with an error if not admin. */
    public static function requireAdmin(): void
    {
        self::require();
        if (!self::isAdmin()) {
            http_response_code(403);
            echo 'You need administrator access for this page.';
            exit;
        }
    }
}
