<?php

namespace App\Core;

use App\Models\LoginAttempt;
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
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCKOUT_WINDOW_SECONDS = 900; // 15 minutes

    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Secure is turned on automatically once the request is over HTTPS
            // (directly, or via a reverse proxy setting X-Forwarded-Proto) — left
            // off otherwise so local HTTP development still works. HttpOnly and
            // SameSite=Lax are always on: the session cookie is never needed by
            // page JS, and Lax stops it being sent on cross-site form posts.
            $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? '') == 443)
                || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $https,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    /**
     * Throttled by normalized email regardless of whether that email maps to
     * a real account — a nonexistent/typo'd email still accrues attempts, so
     * probing doesn't reveal which emails exist by whether throttling ever
     * kicks in. isLockedOut() lets the controller show a specific "too many
     * attempts" message; this method re-checks the same limit itself so the
     * lockout still holds even if a caller skips that check.
     */
    public static function attempt(string $email, string $password): bool
    {
        $normalizedEmail = User::normalizeEmail($email);
        if (self::isLockedOut($normalizedEmail)) {
            return false;
        }

        $user = User::findByEmail($email);
        if (!$user || !$user['is_active'] || !User::verifyPassword($user, $password)) {
            LoginAttempt::record($normalizedEmail);
            return false;
        }

        LoginAttempt::clear($normalizedEmail);
        // Regenerate the session id on every successful login (session fixation
        // defense — an id an attacker planted before login must never become a
        // valid authenticated session) and drop any pre-login CSRF token so a
        // fresh one is issued for the now-authenticated session.
        session_regenerate_id(true);
        unset($_SESSION['_csrf']);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        return true;
    }

    /** Call before attempt() to show a specific "too many attempts" message rather than a generic "incorrect password" on every retry during the lockout window. Accepts either a raw or already-normalized email. */
    public static function isLockedOut(string $email): bool
    {
        return LoginAttempt::recentFailureCount(User::normalizeEmail($email), self::LOCKOUT_WINDOW_SECONDS) >= self::MAX_FAILED_ATTEMPTS;
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
