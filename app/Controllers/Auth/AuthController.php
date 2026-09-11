<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\Controller;

/**
 * Authentication Controller
 * Handles Web Session Login, Validation, Role Redirection, and Logout
 */
class AuthController extends Controller
{
    /**
     * Show login form
     */
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirectByRole(Auth::role());
            return;
        }

        $this->render('auth/login', [
            'pageTitle' => 'Sign In - Secure360',
        ], 'layouts/main');
    }

    /**
     * Process web login request
     */
    public function login(): void
    {
        // 1. Validate CSRF Token
        $csrfToken = (string)($this->request->input('_csrf_token') ?? '');
        if (!\App\Core\Session::validateCsrf($csrfToken)) {
            $this->setFlash('error', 'Security token invalid or expired. Please refresh the page and try again.');
            $this->redirect('/login');
            return;
        }

        $email = trim((string)($this->request->input('email') ?? ''));
        $password = (string)($this->request->input('password') ?? '');

        if ($email === '' || $password === '') {
            $this->setFlash('error', 'Please provide both email and password.');
            $this->redirect('/login');
            return;
        }

        $userModel = new \App\Models\User();
        $user = $userModel->findByEmail($email);

        if (!$user || !Auth::verifyPassword($password, $user['password_hash'])) {
            $this->setFlash('error', 'Invalid email or password.');
            $this->redirect('/login');
            return;
        }

        if ((int)$user['status'] !== STATUS_ACTIVE) {
            $this->setFlash('error', 'Your account is deactivated. Please contact your system administrator.');
            $this->redirect('/login');
            return;
        }

        // Guards access operations exclusively via the Flutter mobile client
        if ($user['role_code'] === ROLE_GUARD) {
            $this->setFlash('error', 'Security Guards must access operations via the Secure360 Flutter Mobile App.');
            $this->redirect('/login');
            return;
        }

        // Setup session data
        $sessionData = [
            'id' => (int)$user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role_code'],
            'role_name' => $user['role_name'],
            'organisation_id' => $user['organization_id'] ? (int)$user['organization_id'] : null,
            'organization_id' => $user['organization_id'] ? (int)$user['organization_id'] : null,
            'organization_name' => $user['organization_name'] ?? null,
        ];

        Auth::login($sessionData);
        $userModel->updateLastLogin((int)$user['id']);

        $redirectParam = (string)($this->request->input('redirect') ?? '');
        if (!empty($redirectParam) && str_starts_with($redirectParam, '/') && !str_starts_with($redirectParam, '//')) {
            // Role-safe redirect validation
            if ($user['role_code'] === ROLE_SUPERADMIN && !str_starts_with($redirectParam, '/admin')) {
                $this->redirect($redirectParam);
                return;
            } elseif ($user['role_code'] === ROLE_ADMIN && !str_starts_with($redirectParam, '/superadmin')) {
                $this->redirect($redirectParam);
                return;
            }
        }

        $this->redirectByRole($user['role_code']);
    }

    /**
     * Terminate web session and logout
     */
    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login');
    }

    /**
     * Role-based redirection helper
     */
    private function redirectByRole(?string $role): void
    {
        if ($role === ROLE_SUPERADMIN) {
            $this->redirect('/superadmin/dashboard');
        } elseif ($role === ROLE_ADMIN) {
            $this->redirect('/admin/dashboard');
        } else {
            $this->redirect('/login');
        }
    }
}
