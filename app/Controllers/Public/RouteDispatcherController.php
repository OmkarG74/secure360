<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Auth;
use App\Core\Controller;

/**
 * Route Dispatcher Controller
 * Provides centralized top-level shortcuts (/dashboard, /clients, /sites, etc.)
 * with role-based redirection to ensure Superadmin and Admin use the same application.
 */
class RouteDispatcherController extends Controller
{
    /**
     * Dispatch /dashboard to the appropriate role-based dashboard
     */
    public function dashboard(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login?redirect=' . urlencode('/dashboard'));
            return;
        }

        $role = Auth::role();
        if ($role === ROLE_SUPERADMIN) {
            $this->redirect('/superadmin/dashboard');
        } elseif ($role === ROLE_ADMIN) {
            $this->redirect('/admin/dashboard');
        } else {
            $this->redirect('/login');
        }
    }

    /**
     * Dispatch /customers
     */
    public function customers(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login?redirect=' . urlencode('/customers'));
            return;
        }

        if (Auth::isSuperAdmin()) {
            $this->redirect('/superadmin/customers');
        } else {
            $this->redirect('/admin/clients-sites');
        }
    }

    /**
     * Dispatch /clients
     */
    public function clients(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login?redirect=' . urlencode('/clients'));
            return;
        }

        if (Auth::isSuperAdmin()) {
            $this->redirect('/superadmin/organisations');
        } else {
            $this->redirect('/admin/clients-sites');
        }
    }

    /**
     * Dispatch /sites
     */
    public function sites(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login?redirect=' . urlencode('/sites'));
            return;
        }

        if (Auth::isSuperAdmin()) {
            $this->redirect('/superadmin/organisations');
        } else {
            $this->redirect('/admin/clients-sites');
        }
    }

    /**
     * Dispatch /guards
     */
    public function guards(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login?redirect=' . urlencode('/guards'));
            return;
        }

        $this->redirect('/admin/guards');
    }

    /**
     * Dispatch /contracts
     */
    public function contracts(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login?redirect=' . urlencode('/contracts'));
            return;
        }

        $this->redirect('/admin/contracts');
    }

    /**
     * Dispatch /attendance
     */
    public function attendance(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login?redirect=' . urlencode('/attendance'));
            return;
        }

        $this->redirect('/admin/attendance');
    }

    /**
     * Dispatch /reports
     */
    public function reports(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login?redirect=' . urlencode('/reports'));
            return;
        }

        $this->redirect('/admin/reports');
    }

    /**
     * Dispatch /settings
     */
    public function settings(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login?redirect=' . urlencode('/settings'));
            return;
        }

        if (Auth::isSuperAdmin()) {
            $this->redirect('/superadmin/settings');
        } else {
            $this->redirect('/admin/settings');
        }
    }
}
