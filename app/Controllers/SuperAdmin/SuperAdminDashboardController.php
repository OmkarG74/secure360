<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

/**
 * Superadmin Dashboard Controller
 * Fetches real platform-wide metrics from secure360_v2
 */
class SuperAdminDashboardController extends Controller
{
    public function index(): void
    {
        $db = Database::getConnection();

        $totalOrgs = (int)$db->query("SELECT COUNT(*) FROM organizations WHERE deleted_at IS NULL")->fetchColumn();
        $activeOrgs = (int)$db->query("SELECT COUNT(*) FROM organizations WHERE status = 0 AND deleted_at IS NULL")->fetchColumn();
        $totalUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn();
        $totalGuards = (int)$db->query("SELECT COUNT(*) FROM guards WHERE deleted_at IS NULL")->fetchColumn();

        $this->render('superadmin/dashboard/index', [
            'pageTitle' => 'Superadmin Master Control Panel',
            'user' => Auth::user(),
            'metrics' => [
                'totalOrgs' => $totalOrgs,
                'activeOrgs' => $activeOrgs,
                'totalUsers' => $totalUsers,
                'totalGuards' => $totalGuards,
            ],
        ], 'layouts/superadmin');
    }
}
