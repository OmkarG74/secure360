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
            'isEdit' => false,
            'guard' => null,
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
                'photo_url' => $photoUrl,
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
     * Show edit form for guard (opens Guard Setup in edit mode with prefilled details)
     */
    public function editForm(Request $request = null, Response $response = null, array $params = []): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $guardId = (int)($params['id'] ?? 0);

        if ($guardId <= 0) {
            $this->setFlash('error', 'Invalid guard ID.');
            $this->redirect('/admin/guards');
            return;
        }

        $guardModel = new Guard();
        $guard = $guardModel->findByGuardId($guardId, $orgId);

        if (!$guard) {
            $this->setFlash('error', 'Guard record not found.');
            $this->redirect('/admin/guards');
            return;
        }

        $this->render('admin/guards/setup', [
            'pageTitle' => 'Edit Guard - ' . $guard['full_name'],
            'organisationId' => $orgId,
            'guard' => $guard,
            'isEdit' => true,
            'nextCode' => $guard['employee_code'] ?? 'GRD-101',
        ], 'layouts/admin');
    }

    /**
     * Update existing guard profile and credentials
     */
    public function updateGuard(Request $request = null, Response $response = null, array $params = []): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $guardId = (int)($params['id'] ?? 0);

        if ($guardId <= 0) {
            $this->setFlash('error', 'Invalid guard ID.');
            $this->redirect('/admin/guards');
            return;
        }

        $guardModel = new Guard();
        $guard = $guardModel->findByGuardId($guardId, $orgId);

        if (!$guard) {
            $this->setFlash('error', 'Guard record not found.');
            $this->redirect('/admin/guards');
            return;
        }

        $fullName = trim((string)$this->request->input('full_name', ''));
        $email = trim((string)$this->request->input('email', ''));
        $phone = trim((string)$this->request->input('phone', ''));
        $status = (int)$this->request->input('status', 0);
        $password = (string)$this->request->input('password', '');

        if ($fullName === '') {
            $this->setFlash('error', 'Full name is required.');
            $this->redirect("/admin/guards/{$guardId}/edit");
            return;
        }

        if ($email === '') {
            $this->setFlash('error', 'Email address is required.');
            $this->redirect("/admin/guards/{$guardId}/edit");
            return;
        }

        $userModel = new User();

        // Check if email changed and is taken by another account
        if (strtolower($email) !== strtolower($guard['email'])) {
            $existing = $userModel->findByEmail($email);
            if ($existing && (int)$existing['id'] !== (int)$guard['user_id']) {
                $this->setFlash('error', 'A user with this email address already exists.');
                $this->redirect("/admin/guards/{$guardId}/edit");
                return;
            }
        }

        if ($password !== '' && strlen($password) < 6) {
            $this->setFlash('error', 'Password must be at least 6 characters.');
            $this->redirect("/admin/guards/{$guardId}/edit");
            return;
        }

        try {
            $userModel->beginTransaction();

            $userData = [
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone ?: null,
                'status' => $status,
            ];

            if ($password !== '') {
                $userData['password_hash'] = Auth::hashPassword($password);
            }

            $guardData = [
                'status' => $status,
            ];

            // Photo upload check
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
                        $guardData['photo_url'] = $photoUrl;
                        $userData['photo_url'] = $photoUrl;
                    }
                }
            }

            $userModel->update((int)$guard['user_id'], $userData);
            $guardModel->update($guardId, $guardData);

            $userModel->commit();

            $this->setFlash('success', "Guard '{$fullName}' updated successfully.");
            $this->redirect('/admin/guards');
        } catch (\Throwable $e) {
            $userModel->rollBack();
            $this->setFlash('error', 'Error updating guard: ' . $e->getMessage());
            $this->redirect("/admin/guards/{$guardId}/edit");
        }
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
