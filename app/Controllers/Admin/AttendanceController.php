<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Attendance;

/**
 * Guard Attendance Management Controller
 * Connected to secure360_v2 `attendance` table
 */
class AttendanceController extends Controller
{
    public function index(): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $attendanceModel = new Attendance();
        $records = $attendanceModel->allByTenant($orgId);

        $this->render('admin/attendance/index', [
            'pageTitle' => 'Guard Attendance & Duty Logs',
            'organisationId' => $orgId,
            'records' => $records,
        ], 'layouts/admin');
    }
}
