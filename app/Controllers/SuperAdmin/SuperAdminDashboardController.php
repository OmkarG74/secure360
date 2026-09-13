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
        $suspendedOrgs = (int)$db->query("SELECT COUNT(*) FROM organizations WHERE status != 0 AND deleted_at IS NULL")->fetchColumn();
        
        // New organisations in the last 30 days (or all if newly installed)
        $newOrgs = (int)$db->query("SELECT COUNT(*) FROM organizations WHERE deleted_at IS NULL AND (created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) OR created_at IS NULL)")->fetchColumn();
        if ($newOrgs === 0 && $totalOrgs > 0) {
            $newOrgs = $totalOrgs;
        }

        $totalUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn();
        $totalGuards = (int)$db->query("SELECT COUNT(*) FROM guards WHERE deleted_at IS NULL")->fetchColumn();

        // 5 most recent organisations
        $recentOrgsStmt = $db->query("SELECT * FROM organizations WHERE deleted_at IS NULL ORDER BY created_at DESC, id DESC LIMIT 5");
        $recentOrgs = $recentOrgsStmt ? $recentOrgsStmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        // Real platform activity derived from recent tenant registrations
        $recentActivity = [];
        foreach ($recentOrgs as $org) {
            $isSuspended = (int)($org['status'] ?? 0) !== 0;
            $recentActivity[] = [
                'type' => $isSuspended ? 'org_suspended' : 'org_onboarded',
                'title' => $isSuspended ? 'Organisation Suspended' : 'Organisation Onboarded',
                'description' => "Tenant account " . ($org['name'] ?? 'Unknown') . " (" . ($org['organization_code'] ?? 'ORG') . ") is " . ($isSuspended ? 'currently suspended.' : 'active and operational.'),
                'created_at' => $org['created_at'] ?? date('Y-m-d H:i:s'),
                'badge' => $isSuspended ? 'Suspended' : 'Active',
                'badge_color' => $isSuspended ? 'amber' : 'green',
            ];
        }

        $this->render('superadmin/dashboard/index', [
            'pageTitle' => 'Superadmin Master Control Panel',
            'user' => Auth::user(),
            'metrics' => [
                'totalOrgs' => $totalOrgs,
                'activeOrgs' => $activeOrgs,
                'suspendedOrgs' => $suspendedOrgs,
                'newOrgs' => $newOrgs,
                'totalUsers' => $totalUsers,
                'totalGuards' => $totalGuards,
            ],
            'recentOrgs' => $recentOrgs,
            'recentActivity' => $recentActivity,
        ], 'layouts/superadmin');
    }
}
