<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Customer;
use App\Models\Site;

/**
 * Clients & Sites Management Controller
 * Business Rule: One Organisation -> Multiple Customers/Clients -> Multiple Sites per Client
 * Connected to secure360_v2 database tables: `customers` and `sites`
 */
class ClientSiteController extends Controller
{
    /**
     * Display list of organisation clients with their associated sites (Screenshot 3 style)
     */
    public function index(Request $request = null, Response $response = null): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $customerModel = new Customer();
        $siteModel = new Site();

        $statusFilter = $this->request->query('status', 'all');
        $searchQuery = trim((string)$this->request->query('search', ''));

        $allCustomers = $customerModel->allByTenant($orgId);

        // Compute counts
        $totalCount = count($allCustomers);
        $activeCount = 0;
        $inactiveCount = 0;

        foreach ($allCustomers as $c) {
            if ((int)$c['status'] === 0) {
                $activeCount++;
            } else {
                $inactiveCount++;
            }
        }

        // Apply filters
        $filtered = array_filter($allCustomers, function ($c) use ($statusFilter, $searchQuery) {
            if ($statusFilter === 'active' && (int)$c['status'] !== 0) {
                return false;
            }
            if ($statusFilter === 'inactive' && (int)$c['status'] === 0) {
                return false;
            }
            if ($searchQuery !== '') {
                $query = strtolower($searchQuery);
                $nameMatches = str_contains(strtolower($c['name'] ?? ''), $query);
                $codeMatches = str_contains(strtolower($c['client_code'] ?? ''), $query);
                $personMatches = str_contains(strtolower($c['contact_person'] ?? ''), $query);
                $emailMatches = str_contains(strtolower($c['email'] ?? ''), $query);
                if (!$nameMatches && !$codeMatches && !$personMatches && !$emailMatches) {
                    return false;
                }
            }
            return true;
        });

        // Attach sites
        $customersWithSites = [];
        foreach ($filtered as $customer) {
            $customer['sites'] = $customerModel->getSites((int)$customer['id'], $orgId);
            $customersWithSites[] = $customer;
        }

        $allSites = $siteModel->allByTenant($orgId);

        $this->render('admin/clients-sites/index', [
            'pageTitle' => 'Clients & Accounts - Secure360',
            'organisationId' => $orgId,
            'customers' => $customersWithSites,
            'sites' => $allSites,
            'statusFilter' => $statusFilter,
            'searchQuery' => $searchQuery,
            'totalCount' => $totalCount,
            'activeCount' => $activeCount,
            'inactiveCount' => $inactiveCount,
        ], 'layouts/admin');
    }

    /**
     * Show form to register a new client with dynamic sites (Screenshot 2 style)
     */
    public function registerForm(): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $generatedCode = 'CLT-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

        $this->render('admin/clients-sites/register', [
            'pageTitle' => 'Register Client - Secure360',
            'organisationId' => $orgId,
            'generatedCode' => $generatedCode,
        ], 'layouts/admin');
    }

    /**
     * Store new client and multiple sites atomically
     */
    public function storeClient(): void
    {
        $orgId = Auth::organisationId() ?? 1;

        $name = trim((string)$this->request->input('name', ''));
        $clientCode = trim((string)$this->request->input('client_code', ''));
        $contactPerson = trim((string)$this->request->input('contact_person', ''));
        $phone = trim((string)$this->request->input('phone', ''));
        $email = trim((string)$this->request->input('email', ''));
        $address = trim((string)$this->request->input('address', ''));
        $status = (int)$this->request->input('status', 0);

        if ($name === '') {
            $this->setFlash('error', 'Client company name is required.');
            $this->redirect('/admin/clients/register');
            return;
        }

        if ($clientCode === '') {
            $cleanName = strtoupper((string)preg_replace('/[^a-zA-Z0-9]/', '', $name));
            $clientCode = 'CLT-' . substr($cleanName, 0, 4) . '-' . rand(100, 999);
        }

        $customerModel = new Customer();
        $siteModel = new Site();

        try {
            $customerModel->beginTransaction();

            $customerId = $customerModel->create([
                'organization_id' => $orgId,
                'client_code' => $clientCode,
                'name' => $name,
                'contact_person' => $contactPerson ?: null,
                'phone' => $phone ?: null,
                'email' => $email ?: null,
                'address' => $address ?: null,
                'status' => $status,
            ]);

            // Handle sites array from dynamic form
            $sitesData = $this->request->input('sites');
            if (is_array($sitesData)) {
                $siteIndex = 1;
                foreach ($sitesData as $siteInput) {
                    if (empty($siteInput['site_name'])) {
                        continue;
                    }
                    $sName = trim((string)$siteInput['site_name']);
                    $sCode = !empty($siteInput['site_code']) 
                        ? trim((string)$siteInput['site_code']) 
                        : 'SITE-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $sName), 0, 4)) . '-' . rand(100, 999);

                    $siteModel->create([
                        'organization_id' => $orgId,
                        'customer_id' => $customerId,
                        'site_code' => $sCode,
                        'site_name' => $sName,
                        'site_address' => !empty($siteInput['site_address']) ? trim((string)$siteInput['site_address']) : null,
                        'area' => !empty($siteInput['area']) ? trim((string)$siteInput['area']) : null,
                        'latitude' => !empty($siteInput['latitude']) ? (float)$siteInput['latitude'] : null,
                        'longitude' => !empty($siteInput['longitude']) ? (float)$siteInput['longitude'] : null,
                        'zone_gate' => !empty($siteInput['zone_gate']) ? trim((string)$siteInput['zone_gate']) : null,
                        'status' => 0,
                    ]);
                    $siteIndex++;
                }
            }

            $customerModel->commit();
            $this->setFlash('success', "Client '{$name}' and associated security sites registered successfully.");
            $this->redirect('/admin/clients-sites');
        } catch (\Throwable $e) {
            $customerModel->rollBack();
            $this->setFlash('error', 'Error registering client: ' . $e->getMessage());
            $this->redirect('/admin/clients/register');
        }
    }

    /**
     * Show edit form for client
     */
    public function editClient(Request $request = null, Response $response = null, array $params = []): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $id = (int)($params['id'] ?? 0);

        $customerModel = new Customer();
        $customer = $customerModel->findByTenant($id, $orgId);

        if (!$customer) {
            $this->setFlash('error', 'Client account not found.');
            $this->redirect('/admin/clients-sites');
            return;
        }

        $customer['sites'] = $customerModel->getSites($id, $orgId);

        $this->render('admin/clients-sites/edit', [
            'pageTitle' => 'Edit Client - ' . $customer['name'],
            'organisationId' => $orgId,
            'client' => $customer,
        ], 'layouts/admin');
    }

    /**
     * Update client details
     */
    public function updateClient(Request $request = null, Response $response = null, array $params = []): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $id = (int)($params['id'] ?? 0);

        $customerModel = new Customer();
        $customer = $customerModel->findByTenant($id, $orgId);

        if (!$customer) {
            $this->setFlash('error', 'Client account not found.');
            $this->redirect('/admin/clients-sites');
            return;
        }

        $name = trim((string)$this->request->input('name', ''));
        if ($name === '') {
            $this->setFlash('error', 'Client company name is required.');
            $this->redirect("/admin/clients/{$id}/edit");
            return;
        }

        $customerModel->update($id, [
            'name' => $name,
            'contact_person' => trim((string)$this->request->input('contact_person', '')) ?: null,
            'phone' => trim((string)$this->request->input('phone', '')) ?: null,
            'email' => trim((string)$this->request->input('email', '')) ?: null,
            'address' => trim((string)$this->request->input('address', '')) ?: null,
            'status' => (int)$this->request->input('status', 0),
        ]);

        $this->setFlash('success', 'Client details updated successfully.');
        $this->redirect('/admin/clients-sites');
    }

    /**
     * Delete / deactivate client
     */
    public function deleteClient(Request $request = null, Response $response = null, array $params = []): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $id = (int)($params['id'] ?? 0);

        $customerModel = new Customer();
        $customer = $customerModel->findByTenant($id, $orgId);

        if ($customer) {
            $customerModel->softDelete($id);
            $this->setFlash('success', "Client '{$customer['name']}' has been removed.");
        }

        $this->redirect('/admin/clients-sites');
    }

    /**
     * Add a site to an existing client
     */
    public function addSiteToClient(Request $request = null, Response $response = null, array $params = []): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $customerId = (int)($params['id'] ?? 0);

        $customerModel = new Customer();
        $customer = $customerModel->findByTenant($customerId, $orgId);

        if (!$customer) {
            $this->setFlash('error', 'Client account not found.');
            $this->redirect('/admin/clients-sites');
            return;
        }

        $siteName = trim((string)$this->request->input('site_name', ''));
        if ($siteName === '') {
            $this->setFlash('error', 'Site name is required.');
            $this->redirect("/admin/clients/{$customerId}/edit");
            return;
        }

        $siteCode = trim((string)$this->request->input('site_code', ''));
        if ($siteCode === '') {
            $siteCode = 'SITE-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $siteName), 0, 4)) . '-' . rand(100, 999);
        }

        $siteModel = new Site();
        $siteModel->create([
            'organization_id' => $orgId,
            'customer_id' => $customerId,
            'site_code' => $siteCode,
            'site_name' => $siteName,
            'site_address' => trim((string)$this->request->input('site_address', '')) ?: null,
            'area' => trim((string)$this->request->input('area', '')) ?: null,
            'latitude' => !empty($this->request->input('latitude')) ? (float)$this->request->input('latitude') : null,
            'longitude' => !empty($this->request->input('longitude')) ? (float)$this->request->input('longitude') : null,
            'zone_gate' => trim((string)$this->request->input('zone_gate', '')) ?: null,
            'status' => 0,
        ]);

        $this->setFlash('success', "Site '{$siteName}' added successfully.");
        $this->redirect("/admin/clients/{$customerId}/edit");
    }

    /**
     * Delete site
     */
    public function deleteSite(Request $request = null, Response $response = null, array $params = []): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $siteId = (int)($params['id'] ?? 0);

        $siteModel = new Site();
        $site = $siteModel->findByTenant($siteId, $orgId);

        if ($site) {
            $siteModel->softDelete($siteId);
            $this->setFlash('success', "Site '{$site['site_name']}' removed.");
        }

        $this->redirect('/admin/clients-sites');
    }
}
