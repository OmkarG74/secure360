<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Organization;
use App\Models\User;

/**
 * Superadmin Organisation Management Controller
 * Connected to secure360_v2 `organizations` table
 */
class OrganisationController extends Controller
{
    /**
     * List all tenant organizations
     */
    public function index(Request $request = null, Response $response = null): void
    {
        $orgModel = new Organization();
        $organizations = $orgModel->allActive();

        $this->render('superadmin/organisations/index', [
            'pageTitle' => 'Tenant Organisations - Superadmin',
            'organizations' => $organizations,
        ], 'layouts/superadmin');
    }

    /**
     * Show organization onboarding form
     */
    public function createForm(): void
    {
        $generatedCode = 'ORG-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

        $this->render('superadmin/organisations/create', [
            'pageTitle' => 'Onboard Organisation - Superadmin',
            'generatedCode' => $generatedCode,
        ], 'layouts/superadmin');
    }

    /**
     * Store new organization and provision multiple tenant admin accounts
     */
    public function store(): void
    {
        $name = trim((string)$this->request->input('name', ''));
        $code = trim((string)$this->request->input('organization_code', ''));
        $contactPerson = trim((string)$this->request->input('contact_person', ''));
        $email = trim((string)$this->request->input('email', ''));
        $phone = trim((string)$this->request->input('phone', ''));
        $address = trim((string)$this->request->input('address', ''));

        // Parse admins list
        $adminsInput = $this->request->input('admins');
        $admins = [];

        if (is_array($adminsInput) && !empty($adminsInput)) {
            foreach ($adminsInput as $admin) {
                if (!is_array($admin)) continue;
                $aName = trim((string)($admin['name'] ?? ''));
                $aEmail = trim((string)($admin['email'] ?? ''));
                $aPassword = (string)($admin['password'] ?? '');

                if ($aEmail !== '' || $aPassword !== '' || $aName !== '') {
                    $admins[] = [
                        'name' => $aName,
                        'email' => $aEmail,
                        'password' => $aPassword,
                    ];
                }
            }
        }

        // Fallback to legacy single admin fields if admins array was not provided
        if (empty($admins)) {
            $adminName = trim((string)$this->request->input('admin_name', ''));
            $adminEmail = trim((string)$this->request->input('admin_email', ''));
            $adminPassword = (string)$this->request->input('admin_password', '');

            if ($adminEmail !== '' || $adminPassword !== '') {
                $admins[] = [
                    'name' => $adminName,
                    'email' => $adminEmail,
                    'password' => $adminPassword,
                ];
            }
        }

        if ($name === '' || empty($admins)) {
            $this->setFlash('error', 'Organisation name and at least one administrator account are required.');
            $this->redirect('/superadmin/organisations/create');
            return;
        }

        $userModel = new User();
        $seenEmails = [];

        // Validate each admin
        foreach ($admins as $index => $admin) {
            $adminNum = $index + 1;
            if ($admin['email'] === '') {
                $this->setFlash('error', "Admin {$adminNum} requires a valid login email address.");
                $this->redirect('/superadmin/organisations/create');
                return;
            }

            if (!filter_var($admin['email'], FILTER_VALIDATE_EMAIL)) {
                $this->setFlash('error', "Admin {$adminNum} has an invalid email format ('{$admin['email']}').");
                $this->redirect('/superadmin/organisations/create');
                return;
            }

            if (strlen($admin['password']) < 6) {
                $this->setFlash('error', "Admin {$adminNum} password must be at least 6 characters.");
                $this->redirect('/superadmin/organisations/create');
                return;
            }

            $lowerEmail = strtolower($admin['email']);
            if (isset($seenEmails[$lowerEmail])) {
                $this->setFlash('error', "Duplicate email address '{$admin['email']}' entered for multiple administrators.");
                $this->redirect('/superadmin/organisations/create');
                return;
            }
            $seenEmails[$lowerEmail] = true;

            // Check if email already exists in database
            $existingUser = $userModel->findByEmail($admin['email']);
            if ($existingUser) {
                $this->setFlash('error', "The email address '{$admin['email']}' is already registered to an existing account.");
                $this->redirect('/superadmin/organisations/create');
                return;
            }
        }

        if ($code === '') {
            $code = 'ORG-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 4)) . '-' . rand(100, 999);
        }

        $orgModel = new Organization();

        try {
            $orgModel->beginTransaction();

            $orgId = $orgModel->create([
                'organization_code' => $code,
                'name' => $name,
                'contact_person' => $contactPerson ?: null,
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'address' => $address ?: null,
                'status' => 0,
            ]);

            // Create all tenant Admin accounts linked to the single organisation
            foreach ($admins as $index => $admin) {
                $adminFullName = $admin['name'] ?: ($name . ' Admin ' . ($index === 0 ? '' : ($index + 1)));
                $userModel->create([
                    'organization_id' => $orgId,
                    'role_id' => 2, // Admin
                    'full_name' => trim($adminFullName),
                    'email' => $admin['email'],
                    'phone' => $phone ?: null,
                    'employee_code' => 'ADM-' . rand(100, 999),
                    'password_hash' => Auth::hashPassword($admin['password']),
                    'status' => 0,
                ]);
            }

            $orgModel->commit();

            $adminCount = count($admins);
            $msg = $adminCount === 1 
                ? "Organisation '{$name}' onboarded successfully with tenant Admin account."
                : "Organisation '{$name}' onboarded successfully with {$adminCount} tenant Administrator accounts.";

            $this->setFlash('success', $msg);
            $this->redirect('/superadmin/organisations');
        } catch (\Throwable $e) {
            $orgModel->rollBack();
            $this->setFlash('error', 'Failed to onboard organisation: ' . $e->getMessage());
            $this->redirect('/superadmin/organisations/create');
        }
    }

    /**
     * Edit organization
     */
    public function editForm(Request $request = null, Response $response = null, array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $orgModel = new Organization();
        $organization = $orgModel->find($id);

        if (!$organization) {
            $this->setFlash('error', 'Organisation not found.');
            $this->redirect('/superadmin/organisations');
            return;
        }

        $this->render('superadmin/organisations/edit', [
            'pageTitle' => 'Edit Organisation - ' . $organization['name'],
            'org' => $organization,
        ], 'layouts/superadmin');
    }

    /**
     * Update organization
     */
    public function update(Request $request = null, Response $response = null, array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $orgModel = new Organization();
        $organization = $orgModel->find($id);

        if (!$organization) {
            $this->setFlash('error', 'Organisation not found.');
            $this->redirect('/superadmin/organisations');
            return;
        }

        $name = trim((string)$this->request->input('name', ''));
        if ($name === '') {
            $this->setFlash('error', 'Organisation name is required.');
            $this->redirect("/superadmin/organisations/{$id}/edit");
            return;
        }

        $orgModel->update($id, [
            'name' => $name,
            'contact_person' => trim((string)$this->request->input('contact_person', '')) ?: null,
            'email' => trim((string)$this->request->input('email', '')) ?: null,
            'phone' => trim((string)$this->request->input('phone', '')) ?: null,
            'address' => trim((string)$this->request->input('address', '')) ?: null,
            'status' => (int)$this->request->input('status', 0),
        ]);

        $this->setFlash('success', 'Organisation updated successfully.');
        $this->redirect('/superadmin/organisations');
    }

    /**
     * Toggle organization status
     */
    public function toggleStatus(Request $request = null, Response $response = null, array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $orgModel = new Organization();
        $organization = $orgModel->find($id);

        if ($organization) {
            $newStatus = (int)$organization['status'] === 0 ? 1 : 0;
            $orgModel->update($id, ['status' => $newStatus]);
            $statusLabel = $newStatus === 0 ? 'activated' : 'suspended';
            $this->setFlash('success', "Organisation '{$organization['name']}' has been {$statusLabel}.");
        }

        $this->redirect('/superadmin/organisations');
    }
}
