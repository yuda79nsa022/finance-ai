<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        Auth::start();
        if (Auth::check()) {
            $this->redirect('/');
            return;
        }
        $this->view('auth/login', [
            'error'  => $this->input('error'),
            'return' => $this->input('return', '/'),
        ]);
    }

    public function login(): void
    {
        Auth::start();
        $email = trim((string) $this->input('email', ''));
        $password = (string) $this->input('password', '');
        $return = $this->input('return', '/');

        if ($email !== '' && Auth::isLockedOut($email)) {
            $this->redirect('/login?error=' . urlencode('Too many failed attempts for this account. Try again in about 15 minutes.') . '&return=' . urlencode($return));
            return;
        }

        if (Auth::attempt($email, $password)) {
            $this->redirect($return && str_starts_with($return, '/') ? $return : '/');
            return;
        }

        $this->redirect('/login?error=' . urlencode('Incorrect email or password.') . '&return=' . urlencode($return));
    }

    public function logout(): void
    {
        Auth::start();
        Auth::logout();
        $this->redirect('/login');
    }

    public function showAccount(): void
    {
        $this->requireAuth();
        $this->view('auth/account', ['error' => $this->input('error'), 'success' => $this->input('success')]);
    }

    public function changePassword(): void
    {
        $this->requireAuth();
        $current = (string) $this->input('current_password', '');
        $new = (string) $this->input('new_password', '');
        $user = \App\Models\User::find(Auth::user()['id']);

        if (!\App\Models\User::verifyPassword($user, $current)) {
            $this->redirect('/account?error=' . urlencode('Current password is incorrect.'));
            return;
        }
        if (strlen($new) < 8) {
            $this->redirect('/account?error=' . urlencode('New password must be at least 8 characters.'));
            return;
        }
        \App\Models\User::updatePassword($user['id'], $new);
        $this->redirect('/account?success=1');
    }
}
