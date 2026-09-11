<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Guard;
use App\Models\User;

/**
 * Guard Management Controller
 * Connected to secure360_v2 `guards` joined with `users`
 * Strictly NO checkboxes on guards list tables
 */
class GuardController extends Controller
{
    /**
     * Display guard roster (Screenshot 5 style)
     */
    public function index(Request $request = null, Response $response = null): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $guardModel = new Guard();

        $statusFilter = $this->request->query('status', 'all');
        $searchQuery = trim((string)$this->request->query('search', ''));

        $allGuards = $guardModel->getGuardsWithDetails($orgId);

        $totalCount = count($allGuards);
        $activeCount = 0;
        $inactiveCount = 0;

        foreach ($allGuards as $g) {
            if ((int)$g['guard_status'] === 0) {
                $activeCount++;
            } else {
                $inactiveCount++;
            }
        }

        $filtered = array_filter($allGuards, function ($g) use ($statusFilter, $searchQuery) {
            if ($statusFilter === 'active' && (int)$g['guard_status'] !== 0) {
                return false;
            }
            if ($statusFilter === 'inactive' && (int)$g['guard_status'] === 0) {
                return false;
            }
            if ($searchQuery !== '') {
                $query = strtolower($searchQuery);
                $nameMatches = str_contains(strtolower($g['full_name'] ?? ''), $query);
                $codeMatches = str_contains(strtolower($g['employee_code'] ?? ''), $query);
                $emailMatches = str_contains(strtolower($g['email'] ?? ''), $query);
                $phoneMatches = str_contains(strtolower($g['phone'] ?? ''), $query);
                $siteMatches = str_contains(strtolower($g['site_name'] ?? ''), $query);
                if (!$nameMatches && !$codeMatches && !$emailMatches && !$phoneMatches && !$siteMatches) {
                    return false;
                }
            }
            return true;
        });

        $this->render('admin/guards/index', [
            'pageTitle' => 'Security Guards Roster - Secure360',
            'organisationId' => $orgId,
            'guards' => array_values($filtered),
            'statusFilter' => $statusFilter,
            'searchQuery' => $searchQuery,
            'totalCount' => $totalCount,
            'activeCount' => $activeCount,
            'inactiveCount' => $inactiveCount,
        ], 'layouts/admin');
    }

    /**
     * Show guard setup form (Screenshot 4 style)
     */
    public function setupForm(): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $guardModel = new Guard();
        $nextCode = $guardModel->getNextEmployeeCode($orgId);

        $this->render('admin/guards/setup', [
            'pageTitle' => 'Guard Setup - Secure360',
            'organisationId' => $orgId,
            'nextCode' => $nextCode,
        ], 'layouts/admin');
    }

    /**
     * Store new guard and provision credentials for Flutter mobile app
     */
    public function storeGuard(): void
    {
        $orgId = Auth::organisationId() ?? 1;

        $fullName = trim((string)$this->request->input('full_name', ''));
        $email = trim((string)$this->request->input('email', ''));
        $phone = trim((string)$this->request->input('phone', ''));
        $employeeCode = trim((string)$this->request->input('employee_code', ''));
        $password = (string)$this->request->input('password', '');
        $status = (int)$this->request->input('status', 0);

        if ($fullName === '' || $email === '' || $password === '') {
            $this->setFlash('error', 'Full name, email address, and mobile password are required.');
            $this->redirect('/admin/guards/setup');
            return;
        }

        $userModel = new User();
        $existing = $userModel->findByEmail($email);
        if ($existing) {
            $this->setFlash('error', 'A user with this email address already exists.');
            $this->redirect('/admin/guards/setup');
            return;
        }

        $guardModel = new Guard();
        if ($employeeCode === '') {
            $employeeCode = $guardModel->getNextEmployeeCode($orgId);
        }

        // Handle profile photo upload if provided
        $photoUrl = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['photo']['tmp_name'];
            $origName = $_FILES['photo']['name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($ext, $allowed, true)) {
                $uploadDir = ROOT_PATH . '/public/uploads/guards';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $filename = 'guard_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $target = $uploadDir . '/' . $filename;
                if (move_uploaded_file($tmpPath, $target)) {
                    $photoUrl = 'uploads/guards/' . $filename;
                }
            }
        }

        try {
            $userModel->beginTransaction();

            $passwordHash = Auth::hashPassword($password);

            $userId = $userModel->create([
                'organization_id' => $orgId,
                'role_id' => 3, // Guard
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone ?: null,
                'employee_code' => $employeeCode,
                'password_hash' => $passwordHash,
                'status' => $status,
            ]);

            $guardModel->create([
                'user_id' => $userId,
                'photo_url' => $photoUrl,
                'status' => $status,
            ]);

            $userModel->commit();
            $this->setFlash('success', "Guard '{$fullName}' ({$employeeCode}) provisioned successfully for mobile duty.");
            $this->redirect('/admin/guards');
        } catch (\Throwable $e) {
            $userModel->rollBack();
            $this->setFlash('error', 'Error provisioning guard: ' . $e->getMessage());
            $this->redirect('/admin/guards/setup');
        }
    }

    /**
     * Show edit form for guard
     */
    public function editForm(Request $request = null, Response $response = null, array $params = []): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $guardId = (int)($params['id'] ?? 0);

        $guardModel = new Guard();
        $guard = $guardModel->findByGuardId($guardId, $orgId);

        if (!$guard) {
            $this->setFlash('error', 'Guard record not found.');
            $this->redirect('/admin/guards');
            return;
        }

        $this->render('admin/guards/edit', [
            'pageTitle' => 'Edit Guard - ' . $guard['full_name'],
            'organisationId' => $orgId,
            'guard' => $guard,
        ], 'layouts/admin');
    }

    /**
     * Update guard profile and credentials
     */
    public function updateGuard(Request $request = null, Response $response = null, array $params = []): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $guardId = (int)($params['id'] ?? 0);

        $guardModel = new Guard();
        $guard = $guardModel->findByGuardId($guardId, $orgId);

        if (!$guard) {
            $this->setFlash('error', 'Guard record not found.');
            $this->redirect('/admin/guards');
            return;
        }

        $fullName = trim((string)$this->request->input('full_name', ''));
        $phone = trim((string)$this->request->input('phone', ''));
        $status = (int)$this->request->input('status', 0);
        $password = (string)$this->request->input('password', '');

        if ($fullName === '') {
            $this->setFlash('error', 'Full name is required.');
            $this->redirect("/admin/guards/{$guardId}/edit");
            return;
        }

        $userModel = new User();
        $userData = [
            'full_name' => $fullName,
            'phone' => $phone ?: null,
            'status' => $status,
        ];

        if ($password !== '') {
            $userData['password_hash'] = Auth::hashPassword($password);
        }

        $userModel->update((int)$guard['user_id'], $userData);

        // Photo upload check
        $guardData = ['status' => $status];
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['photo']['tmp_name'];
            $origName = $_FILES['photo']['name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($ext, $allowed, true)) {
                $uploadDir = ROOT_PATH . '/public/uploads/guards';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $filename = 'guard_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $target = $uploadDir . '/' . $filename;
                if (move_uploaded_file($tmpPath, $target)) {
                    $guardData['photo_url'] = 'uploads/guards/' . $filename;
                }
            }
        }

        $guardModel->update($guardId, $guardData);

        $this->setFlash('success', "Guard profile updated successfully.");
        $this->redirect('/admin/guards');
    }

    /**
     * Soft delete guard
     */
    public function deleteGuard(Request $request = null, Response $response = null, array $params = []): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $guardId = (int)($params['id'] ?? 0);

        $guardModel = new Guard();
        $guard = $guardModel->findByGuardId($guardId, $orgId);

        if ($guard) {
            $guardModel->softDelete($guardId);
            $userModel = new User();
            $userModel->softDelete((int)$guard['user_id']);
            $this->setFlash('success', "Guard '{$guard['full_name']}' deactivated.");
        }

        $this->redirect('/admin/guards');
    }
}
