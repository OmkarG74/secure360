<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\ExcelExportService;
use App\Services\PdfInvoiceService;
use App\Services\PdfReportService;
use App\Services\SubscriptionService;

/**
 * Superadmin Organisation Management Controller
 * Connected to secure360_v2 `organizations` table
 */
class OrganisationController extends Controller
{
    /**
     * List all tenant organizations with search and status filtering
     */
    public function index(Request $request = null, Response $response = null): void
    {
        $status = (string)($this->request ? $this->request->query('status', 'all') : ($request ? $request->query('status', 'all') : 'all'));
        $search = trim((string)($this->request ? ($this->request->query('q') ?? $this->request->query('search', '')) : ($request ? ($request->query('q') ?? $request->query('search', '')) : '')));

        $orgModel = new Organization();
        $organizations = $orgModel->getFilteredOrganisations(['status' => $status, 'search' => $search]);
        $statusCounts = $orgModel->getStatusCounts($search);

        $this->render('superadmin/organisations/index', [
            'pageTitle' => 'Tenant Organisations - Superadmin',
            'organizations' => $organizations,
            'statusCounts' => $statusCounts,
            'currentStatus' => $status,
            'searchQuery' => $search,
        ], 'layouts/superadmin');
    }

    /**
     * Export filtered organisations dataset to Excel (.xlsx)
     * Ignores pagination and exports the complete filtered dataset.
     */
    public function exportExcel(Request $request = null, Response $response = null): void
    {
        $status = (string)($this->request ? $this->request->query('status', 'all') : ($request ? $request->query('status', 'all') : 'all'));
        $search = trim((string)($this->request ? ($this->request->query('q') ?? $this->request->query('search', '')) : ($request ? ($request->query('q') ?? $request->query('search', '')) : '')));

        $orgModel = new Organization();
        // Server-side filtered query with no limit
        $organizations = $orgModel->getFilteredOrganisations(['status' => $status, 'search' => $search], limit: null);

        $statusLabel = 'All';
        if ($status === '0') $statusLabel = 'Active';
        elseif ($status === '1') $statusLabel = 'Suspended';

        $user = Auth::user();
        $meta = [
            'status_filter' => $statusLabel,
            'search' => $search,
            'generated_by' => $user ? ($user['full_name'] . ' (' . $user['email'] . ')') : 'Superadmin',
        ];

        $exportService = new ExcelExportService();
        $exportService->exportOrganisations($organizations, $meta);
    }

    /**
     * Export filtered organisations dataset to PDF Report
     * Ignores pagination and exports the complete filtered dataset.
     */
    public function exportPdf(Request $request = null, Response $response = null): void
    {
        $status = (string)($this->request ? $this->request->query('status', 'all') : ($request ? $request->query('status', 'all') : 'all'));
        $search = trim((string)($this->request ? ($this->request->query('q') ?? $this->request->query('search', '')) : ($request ? ($request->query('q') ?? $request->query('search', '')) : '')));

        $orgModel = new Organization();
        // Server-side filtered query with no limit
        $organizations = $orgModel->getFilteredOrganisations(['status' => $status, 'search' => $search], limit: null);

        $statusLabel = 'All';
        if ($status === '0') $statusLabel = 'Active';
        elseif ($status === '1') $statusLabel = 'Suspended';

        $user = Auth::user();
        $meta = [
            'status_filter' => $statusLabel,
            'search' => $search,
            'generated_by' => $user ? ($user['full_name'] . ' (' . $user['email'] . ')') : 'Superadmin',
        ];

        $pdfService = new PdfReportService();
        $pdfService->exportOrganisations($organizations, $meta);
    }

    /**
     * Display Organisation Overview page
     */
    public function show(Request $request = null, Response $response = null, array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $orgModel = new Organization();
        $org = $orgModel->find($id);

        if (!$org) {
            $this->setFlash('error', 'Organisation not found.');
            $this->redirect('/superadmin/organisations');
            return;
        }

        $userModel = new User();
        $admins = $userModel->findAdminsByOrganization($id);

        $this->render('superadmin/organisations/show', [
            'pageTitle' => 'Organisation Overview - ' . $org['name'],
            'org' => $org,
            'admins' => $admins,
        ], 'layouts/superadmin');
    }

    /**
     * Add an additional Administrator to this organisation
     */
    public function addAdmin(Request $request = null, Response $response = null, array $params = []): void
    {
        $orgId = (int)($params['id'] ?? 0);
        $orgModel = new Organization();
        $org = $orgModel->find($orgId);

        if (!$org) {
            $this->setFlash('error', 'Organisation not found.');
            $this->redirect('/superadmin/organisations');
            return;
        }

        $fullName = trim((string)$this->request->input('full_name', ''));
        $email = trim((string)$this->request->input('email', ''));
        $phone = trim((string)$this->request->input('phone', ''));
        $password = (string)$this->request->input('password', '');

        if ($fullName === '' || $email === '' || $password === '') {
            $this->setFlash('error', 'Full name, email, and password are required.');
            $this->redirect("/superadmin/organisations/{$orgId}");
            return;
        }

        if ($phone !== '' && !preg_match('/^[0-9+\-\s().]{6,25}$/', $phone)) {
            $this->setFlash('error', 'Please enter a valid phone number format.');
            $this->redirect("/superadmin/organisations/{$orgId}");
            return;
        }

        $userModel = new User();
        if ($userModel->findByEmail($email)) {
            $this->setFlash('error', "A user with email '{$email}' already exists.");
            $this->redirect("/superadmin/organisations/{$orgId}");
            return;
        }

        $userModel->create([
            'organization_id' => $orgId,
            'role_id' => 2, // Admin
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone ?: null,
            'employee_code' => 'ADM-' . rand(100, 999),
            'password_hash' => Auth::hashPassword($password),
            'status' => 0,
        ]);

        $subService = new SubscriptionService();
        $subService->logActivity($orgId, Auth::id(), 'admin_added', 'Admin Added', [
            'organization_id' => $orgId,
            'admin_name' => $fullName,
            'email' => $email,
        ]);

        $this->setFlash('success', "Organisation Admin '{$fullName}' added successfully.");
        $this->redirect("/superadmin/organisations/{$orgId}");
    }

    /**
     * Update an individual Organisation Admin
     */
    public function updateAdmin(Request $request = null, Response $response = null, array $params = []): void
    {
        $orgId = (int)($params['id'] ?? 0);
        $adminId = (int)($params['adminId'] ?? $this->request->input('admin_id', 0));

        $userModel = new User();
        $admin = $userModel->find($adminId);

        if (!$admin || (int)$admin['organization_id'] !== $orgId || (int)$admin['role_id'] !== 2) {
            $this->setFlash('error', 'Admin account not found.');
            $this->redirect("/superadmin/organisations/{$orgId}");
            return;
        }

        $fullName = trim((string)$this->request->input('full_name', ''));
        $email = trim((string)$this->request->input('email', ''));
        $phone = trim((string)$this->request->input('phone', ''));
        $password = (string)$this->request->input('password', '');

        if ($fullName === '' || $email === '') {
            $this->setFlash('error', 'Full name and email are required.');
            $this->redirect("/superadmin/organisations/{$orgId}");
            return;
        }

        if ($phone !== '' && !preg_match('/^[0-9+\-\s().]{6,25}$/', $phone)) {
            $this->setFlash('error', 'Please enter a valid phone number format.');
            $this->redirect("/superadmin/organisations/{$orgId}");
            return;
        }

        $existing = $userModel->findByEmail($email);
        if ($existing && (int)$existing['id'] !== $adminId) {
            $this->setFlash('error', "Another user already exists with email '{$email}'.");
            $this->redirect("/superadmin/organisations/{$orgId}");
            return;
        }

        $updateData = [
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone ?: null,
        ];

        if ($password !== '') {
            $updateData['password_hash'] = Auth::hashPassword($password);
        }

        $userModel->update($adminId, $updateData);

        $this->setFlash('success', "Administrator '{$fullName}' updated successfully.");
        $this->redirect("/superadmin/organisations/{$orgId}");
    }

    /**
     * Toggle status for an individual Organisation Admin (Suspend / Activate)
     */
    public function toggleAdminStatus(Request $request = null, Response $response = null, array $params = []): void
    {
        $orgId = (int)($params['id'] ?? 0);
        $adminId = (int)($params['adminId'] ?? $this->request->input('admin_id', 0));

        $userModel = new User();
        $admin = $userModel->find($adminId);

        if (!$admin || (int)$admin['organization_id'] !== $orgId || (int)$admin['role_id'] !== 2) {
            $this->setFlash('error', 'Administrator account not found.');
            $this->redirect("/superadmin/organisations/{$orgId}");
            return;
        }

        $newStatus = (int)$admin['status'] === 0 ? 1 : 0;
        $userModel->update($adminId, ['status' => $newStatus]);
        $statusLabel = $newStatus === 0 ? 'activated' : 'suspended';

        $subService = new SubscriptionService();
        $subService->logActivity($orgId, Auth::id(), 'admin_' . $statusLabel, "Admin " . ucfirst($statusLabel), [
            'organization_id' => $orgId,
            'admin_id' => $adminId,
            'admin_name' => $admin['full_name'],
            'new_status' => $newStatus,
        ]);

        $this->setFlash('success', "Administrator '{$admin['full_name']}' has been {$statusLabel}.");
        $this->redirect("/superadmin/organisations/{$orgId}");
    }

    /**
     * Show organization onboarding form with subscription settings
     */
    public function createForm(): void
    {
        $generatedCode = 'ORG-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $settingModel = new SystemSetting();
        $defaultPrice = $settingModel->getDefaultPricePerGuard();
        $currency = $settingModel->getCurrency();

        $this->render('superadmin/organisations/create', [
            'pageTitle' => 'Onboard Organisation - Superadmin',
            'generatedCode' => $generatedCode,
            'defaultPrice' => $defaultPrice,
            'currency' => $currency,
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
                $aPhone = trim((string)($admin['phone'] ?? ''));
                $aPassword = (string)($admin['password'] ?? '');

                if ($aEmail !== '' || $aPassword !== '' || $aName !== '') {
                    $admins[] = [
                        'name' => $aName,
                        'email' => $aEmail,
                        'phone' => $aPhone,
                        'password' => $aPassword,
                    ];
                }
            }
        }

        // Fallback to legacy single admin fields if admins array was not provided
        if (empty($admins)) {
            $adminName = trim((string)$this->request->input('admin_name', ''));
            $adminEmail = trim((string)$this->request->input('admin_email', ''));
            $adminPhone = trim((string)$this->request->input('admin_phone', ''));
            $adminPassword = (string)$this->request->input('admin_password', '');

            if ($adminEmail !== '' || $adminPassword !== '') {
                $admins[] = [
                    'name' => $adminName,
                    'email' => $adminEmail,
                    'phone' => $adminPhone,
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

            if (!empty($admin['phone']) && !preg_match('/^[0-9+\-\s().]{6,25}$/', $admin['phone'])) {
                $this->setFlash('error', "Admin {$adminNum} has an invalid phone number format.");
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
                    'phone' => !empty($admin['phone']) ? $admin['phone'] : null,
                    'employee_code' => 'ADM-' . rand(100, 999),
                    'password_hash' => Auth::hashPassword($admin['password']),
                    'status' => 0,
                ]);
            }
            $orgModel->commit();

            $adminCount = count($admins);
            $msg = $adminCount === 1 
                ? "Organisation '{$name}' onboarded successfully. You can configure its subscription in the Subscriptions module."
                : "Organisation '{$name}' onboarded successfully with {$adminCount} tenant Administrator accounts. You can configure its subscription in the Subscriptions module.";

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

        $userModel = new User();
        $admins = $userModel->findAdminsByOrganization($id);

        $this->render('superadmin/organisations/edit', [
            'pageTitle' => 'Edit Organisation - ' . $organization['name'],
            'org' => $organization,
            'admins' => $admins,
        ], 'layouts/superadmin');
    }

    /**
     * Update organization and its administrators
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

        $userModel = new User();

        // 1. Update Organization Entity
        $orgModel->update($id, [
            'name' => $name,
            'contact_person' => trim((string)$this->request->input('contact_person', '')) ?: null,
            'email' => trim((string)$this->request->input('email', '')) ?: null,
            'phone' => trim((string)$this->request->input('phone', '')) ?: null,
            'address' => trim((string)$this->request->input('address', '')) ?: null,
            'status' => (int)$this->request->input('status', 0),
        ]);

        // 2. Update Existing Administrators (if provided)
        $adminsInput = (array)$this->request->input('admins', []);
        foreach ($adminsInput as $adminId => $adminData) {
            $adminId = (int)$adminId;
            $admin = $userModel->find($adminId);
            if (!$admin || (int)$admin['organization_id'] !== $id || (int)$admin['role_id'] !== 2) {
                continue;
            }

            $fullName = trim((string)($adminData['name'] ?? ''));
            $email = trim((string)($adminData['email'] ?? ''));
            $phone = trim((string)($adminData['phone'] ?? ''));
            $password = (string)($adminData['password'] ?? '');
            $status = isset($adminData['status']) ? (int)$adminData['status'] : (int)$admin['status'];

            if ($fullName === '' || $email === '') {
                continue;
            }

            if ($phone !== '' && !preg_match('/^[0-9+\-\s().]{6,25}$/', $phone)) {
                $this->setFlash('error', "Invalid phone number format for administrator '{$fullName}'.");
                $this->redirect("/superadmin/organisations/{$id}/edit");
                return;
            }

            // Check duplicate email
            $existing = $userModel->findByEmail($email);
            if ($existing && (int)$existing['id'] !== $adminId) {
                $this->setFlash('error', "Email '{$email}' is already registered to another account.");
                $this->redirect("/superadmin/organisations/{$id}/edit");
                return;
            }

            $updateData = [
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone ?: null,
                'status' => $status,
            ];

            if ($password !== '') {
                if (strlen($password) < 6) {
                    $this->setFlash('error', "Password for {$fullName} must be at least 6 characters.");
                    $this->redirect("/superadmin/organisations/{$id}/edit");
                    return;
                }
                $updateData['password_hash'] = Auth::hashPassword($password);
            }

            $userModel->update($adminId, $updateData);
        }

        // 3. Add New Administrators (if provided)
        $newAdmins = (array)$this->request->input('new_admins', []);
        foreach ($newAdmins as $newAdmin) {
            $newEmail = trim((string)($newAdmin['email'] ?? ''));
            $newName = trim((string)($newAdmin['name'] ?? ''));
            $newPassword = (string)($newAdmin['password'] ?? '');
            $newPhone = trim((string)($newAdmin['phone'] ?? ''));

            if ($newEmail === '' && $newName === '' && $newPassword === '') {
                continue;
            }

            if ($newName === '' || $newEmail === '' || strlen($newPassword) < 6) {
                $this->setFlash('error', 'New administrator requires name, valid email, and a password of at least 6 characters.');
                $this->redirect("/superadmin/organisations/{$id}/edit");
                return;
            }

            if ($newPhone !== '' && !preg_match('/^[0-9+\-\s().]{6,25}$/', $newPhone)) {
                $this->setFlash('error', "Invalid phone number format for new administrator '{$newName}'.");
                $this->redirect("/superadmin/organisations/{$id}/edit");
                return;
            }

            $existing = $userModel->findByEmail($newEmail);
            if ($existing) {
                $this->setFlash('error', "Email '{$newEmail}' is already registered to an existing account.");
                $this->redirect("/superadmin/organisations/{$id}/edit");
                return;
            }

            $userModel->create([
                'organization_id' => $id,
                'role_id' => 2, // Admin
                'full_name' => $newName,
                'email' => $newEmail,
                'phone' => $newPhone ?: null,
                'employee_code' => 'ADM-' . rand(100, 999),
                'password_hash' => Auth::hashPassword($newPassword),
                'status' => 0,
            ]);
        }

        $this->setFlash('success', 'Organisation and administrator details updated successfully.');
        $this->redirect("/superadmin/organisations/{$id}");
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

            // Audit
            $subService = new SubscriptionService();
            $subService->logActivity($id, Auth::id(), 'org_' . $statusLabel, "Organisation " . ucfirst($statusLabel), [
                'organization_id' => $id,
                'name' => $organization['name'],
                'status' => $newStatus,
            ]);
        }

        $this->redirect('/superadmin/organisations');
    }

    /**
     * View and manage subscription details for a specific organisation
     */
    public function showSubscription(Request $request = null, Response $response = null, array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $orgModel = new Organization();
        $org = $orgModel->find($id);

        if (!$org) {
            $this->setFlash('error', 'Organisation not found.');
            $this->redirect('/superadmin/organisations');
            return;
        }

        $subModel = new Subscription();
        $sub = $subModel->findCurrentByOrganization($id);

        if ($sub) {
            $this->redirect('/superadmin/subscriptions/' . $sub['id']);
            return;
        }

        $this->setFlash('info', "Organisation '{$org['name']}' currently has no subscription. You can create one below.");
        $this->redirect('/superadmin/subscriptions/create?org_id=' . $id);
    }

    /**
     * Update guard limit or price for an organisation
     */
    public function updateSubscription(Request $request = null, Response $response = null, array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $orgModel = new Organization();
        $org = $orgModel->find($id);

        if (!$org) {
            $this->setFlash('error', 'Organisation not found.');
            $this->redirect('/superadmin/organisations');
            return;
        }

        $newLimit = (int)$this->request->input('guard_limit', 0);
        $newPriceInput = $this->request->input('price_per_guard');
        $newPrice = ($newPriceInput !== null && $newPriceInput !== '') ? (float)$newPriceInput : null;

        if ($newLimit <= 0) {
            $this->setFlash('error', 'Guard limit must be a positive number greater than 0.');
            $this->redirect("/superadmin/organisations/{$id}/subscription");
            return;
        }

        if ($newPrice !== null && $newPrice < 0) {
            $this->setFlash('error', 'Price per guard cannot be negative.');
            $this->redirect("/superadmin/organisations/{$id}/subscription");
            return;
        }

        try {
            $subService = new SubscriptionService();
            $result = $subService->updateGuardLimit($id, $newLimit, $newPrice, Auth::id());

            $msg = "Guard limit updated to {$result['new_limit']} guards (Amount: Rs. " . number_format($result['new_amount'], 2) . ").";
            if (!empty($result['invoice_number'])) {
                $msg .= " Upgrade invoice {$result['invoice_number']} generated.";
            }

            $this->setFlash('success', $msg);
        } catch (\InvalidArgumentException $e) {
            $this->setFlash('error', $e->getMessage());
        } catch (\Throwable $e) {
            $this->setFlash('error', 'Failed to update subscription: ' . $e->getMessage());
        }

        $this->redirect("/superadmin/organisations/{$id}/subscription");
    }

    /**
     * Renew subscription with new billing period
     */
    public function renewSubscription(Request $request = null, Response $response = null, array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $orgModel = new Organization();
        $org = $orgModel->find($id);

        if (!$org) {
            $this->setFlash('error', 'Organisation not found.');
            $this->redirect('/superadmin/organisations');
            return;
        }

        $guardLimit = (int)$this->request->input('guard_limit', 30);
        $pricePerGuard = (float)$this->request->input('price_per_guard', 500);
        $startDate = trim((string)$this->request->input('start_date', date('Y-m-d')));
        $endDate = trim((string)$this->request->input('end_date', date('Y-m-d', strtotime('+1 year'))));

        if ($guardLimit <= 0 || $pricePerGuard < 0) {
            $this->setFlash('error', 'Please provide a valid guard limit and price per guard.');
            $this->redirect("/superadmin/organisations/{$id}/subscription");
            return;
        }

        try {
            $subService = new SubscriptionService();
            $result = $subService->renewSubscription($id, [
                'guard_limit' => $guardLimit,
                'price_per_guard' => $pricePerGuard,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'notes' => 'Renewed by Superadmin',
            ], Auth::id());

            $this->setFlash('success', "Subscription renewed successfully! Invoice {$result['invoice_number']} generated (Total: Rs. " . number_format($result['total_amount'], 2) . ").");
        } catch (\InvalidArgumentException $e) {
            $this->setFlash('error', $e->getMessage());
        } catch (\Throwable $e) {
            $this->setFlash('error', 'Failed to renew subscription: ' . $e->getMessage());
        }

        $this->redirect("/superadmin/organisations/{$id}/subscription");
    }

    /**
     * Dedicated Billing & Invoices History view for an organisation
     */
    public function invoices(Request $request = null, Response $response = null, array $params = []): void
    {
        $id = (int)($params['id'] ?? 0);
        $orgModel = new Organization();
        $org = $orgModel->find($id);

        if (!$org) {
            $this->setFlash('error', 'Organisation not found.');
            $this->redirect('/superadmin/organisations');
            return;
        }

        $invoiceModel = new Invoice();
        $page = max(1, (int)$this->request->query('page', 1));
        $pageSize = 10;
        $totalRecords = $invoiceModel->countByTenant($id);
        $totalPages = max(1, (int)ceil($totalRecords / $pageSize));
        $offset = ($page - 1) * $pageSize;

        $invoices = $invoiceModel->paginateByTenant($id, $pageSize, $offset);

        $this->render('superadmin/organisations/invoices', [
            'pageTitle' => 'Invoices - ' . $org['name'],
            'org' => $org,
            'invoices' => $invoices,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'pageSize' => $pageSize,
        ], 'layouts/superadmin');
    }

    /**
     * Download Invoice PDF as Superadmin
     */
    public function downloadInvoice(Request $request = null, Response $response = null, array $params = []): void
    {
        $invoiceId = (int)($params['id'] ?? $this->request->query('id', 0));
        $invoiceModel = new Invoice();
        $invoice = $invoiceModel->findWithDetails($invoiceId);

        if (!$invoice) {
            $this->setFlash('error', 'Invoice not found.');
            $this->redirect('/superadmin/organisations');
            return;
        }

        $pdfService = new PdfInvoiceService();
        $pdfContent = $pdfService->generate($invoice);

        $filename = "Invoice_{$invoice['invoice_number']}.pdf";

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $pdfContent;
        exit;
    }
}
