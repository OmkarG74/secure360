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
     * Store new organization and provision root admin account
     */
    public function store(): void
    {
        $name = trim((string)$this->request->input('name', ''));
        $code = trim((string)$this->request->input('organization_code', ''));
        $contactPerson = trim((string)$this->request->input('contact_person', ''));
        $email = trim((string)$this->request->input('email', ''));
        $phone = trim((string)$this->request->input('phone', ''));
        $address = trim((string)$this->request->input('address', ''));

        // Admin account info
        $adminName = trim((string)$this->request->input('admin_name', ''));
        $adminEmail = trim((string)$this->request->input('admin_email', ''));
        $adminPassword = (string)$this->request->input('admin_password', '');

        if ($name === '' || $adminEmail === '' || $adminPassword === '') {
            $this->setFlash('error', 'Organisation name, Admin email, and Admin password are required.');
            $this->redirect('/superadmin/organisations/create');
            return;
        }

        if ($code === '') {
            $code = 'ORG-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 4)) . '-' . rand(100, 999);
        }

        $orgModel = new Organization();
        $userModel = new User();

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

            // Create tenant Admin user
            $userModel->create([
                'organization_id' => $orgId,
                'role_id' => 2, // Admin
                'full_name' => $adminName ?: $name . ' Admin',
                'email' => $adminEmail,
                'phone' => $phone ?: null,
                'employee_code' => 'ADM-' . rand(100, 999),
                'password_hash' => Auth::hashPassword($adminPassword),
                'status' => 0,
            ]);

            $orgModel->commit();
            $this->setFlash('success', "Organisation '{$name}' onboarded successfully with tenant Admin account.");
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
