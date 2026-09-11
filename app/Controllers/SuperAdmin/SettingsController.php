<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;

/**
 * Superadmin Platform Settings Controller
 * Global platform configurations, system logs, security policies
 */
class SettingsController extends Controller
{
    public function index(): void
    {
        $this->render('superadmin/settings/index', [
            'pageTitle' => 'Superadmin Global Platform Settings',
        ], 'layouts/superadmin');
    }
}
