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

        $filteredValues = array_values($filtered);
        $totalFiltered = count($filteredValues);
        $page = max(1, (int)$this->request->query('page', 1));
        $pageSize = 10;
        $totalPages = max(1, (int)ceil($totalFiltered / $pageSize));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $pageSize;
        $pagedCustomers = array_slice($filteredValues, $offset, $pageSize);

        // Attach sites to paged customers
        $customersWithSites = [];
        foreach ($pagedCustomers as $customer) {
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
            'currentPage' => $page,
            'pageSize' => $pageSize,
            'totalRecords' => $totalFiltered,
            'totalPages' => $totalPages,
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
                        : $siteModel->getNextSiteCode($orgId);

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
        $siteModel = new Site();
        if ($siteCode === '') {
            $siteCode = $siteModel->getNextSiteCode($orgId);
        }

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
     * Update an existing site record
     */
    public function updateSite(Request $request = null, Response $response = null, array $params = []): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $siteId = (int)($params['id'] ?? 0);

        $siteModel = new Site();
        $site = $siteModel->findByTenant($siteId, $orgId);

        if (!$site) {
            if ($this->request->isAjax() || str_contains($this->request->header('Accept', ''), 'application/json')) {
                $this->json(['success' => false, 'message' => 'Site not found.'], 404);
                return;
            }
            $this->setFlash('error', 'Site not found.');
            $this->redirect('/admin/clients-sites');
            return;
        }

        $siteName = trim((string)$this->request->input('site_name', ''));
        if ($siteName === '') {
            if ($this->request->isAjax() || str_contains($this->request->header('Accept', ''), 'application/json')) {
                $this->json(['success' => false, 'message' => 'Site name is required.'], 400);
                return;
            }
            $this->setFlash('error', 'Site name is required.');
            $this->redirect("/admin/clients/{$site['customer_id']}/edit");
            return;
        }

        $siteCode = trim((string)$this->request->input('site_code', '')) ?: $site['site_code'];
        $siteAddress = trim((string)$this->request->input('site_address', '')) ?: null;
        $zoneGate = trim((string)$this->request->input('zone_gate', '')) ?: null;
        $latInput = $this->request->input('latitude');
        $lngInput = $this->request->input('longitude');
        $latitude = ($latInput !== null && $latInput !== '') ? (float)$latInput : null;
        $longitude = ($lngInput !== null && $lngInput !== '') ? (float)$lngInput : null;

        $siteModel->update($siteId, [
            'site_name' => $siteName,
            'site_code' => $siteCode,
            'site_address' => $siteAddress,
            'zone_gate' => $zoneGate,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        if ($this->request->isAjax() || str_contains($this->request->header('Accept', ''), 'application/json')) {
            $this->json([
                'success' => true,
                'message' => "Site '{$siteName}' updated successfully.",
                'site' => [
                    'id' => $siteId,
                    'site_name' => $siteName,
                    'site_code' => $siteCode,
                    'site_address' => $siteAddress,
                    'zone_gate' => $zoneGate,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]
            ]);
            return;
        }

        $this->setFlash('success', "Site '{$siteName}' updated successfully.");
        $this->redirect("/admin/clients/{$site['customer_id']}/edit");
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

        if (!$site) {
            if ($this->request->isAjax() || str_contains($this->request->header('Accept', ''), 'application/json')) {
                $this->json(['success' => false, 'message' => 'Site not found.'], 404);
                return;
            }
            $this->setFlash('error', 'Site not found.');
            $this->redirect('/admin/clients-sites');
            return;
        }

        $siteModel->softDelete($siteId);
        $this->setFlash('success', "Site '{$site['site_name']}' removed.");

        if ($this->request->isAjax() || str_contains($this->request->header('Accept', ''), 'application/json')) {
            $this->json([
                'success' => true,
                'message' => "Site '{$site['site_name']}' removed successfully.",
                'site_id' => $siteId
            ]);
            return;
        }

        if (!empty($site['customer_id'])) {
            $this->redirect("/admin/clients/{$site['customer_id']}/edit");
            return;
        }

        $this->redirect('/admin/clients-sites');
    }

    /**
     * Ola Maps Places Autocomplete proxy
     */
    public function autocomplete(): void
    {
        $input = trim((string)($this->request->query('input') ?? $this->request->input('input', '')));

        if (mb_strlen($input) < 3) {
            $this->json([
                'success' => false,
                'message' => 'Minimum 3 characters required.',
                'predictions' => [],
            ], 400);
            return;
        }

        $apiKey = config('app.maps.ola_api_key') ?: getenv('OLA_MAPS_API_KEY') ?: ($_ENV['OLA_MAPS_API_KEY'] ?? '');
        if (empty($apiKey)) {
            $this->json([
                'success' => false,
                'message' => 'Ola Maps API key is not configured.',
                'predictions' => [],
            ], 500);
            return;
        }

        $queryParams = [
            'input' => $input,
            'api_key' => $apiKey,
            'language' => 'en',
        ];

        // Optional location bias if provided (lat,lng)
        $location = trim((string)($this->request->query('location') ?? $this->request->input('location', '')));
        if ($location !== '' && preg_match('/^-?\d+(\.\d+)?,-?\d+(\.\d+)?$/', $location)) {
            $queryParams['location'] = $location;
        }

        $url = 'https://api.olamaps.io/places/v1/autocomplete?' . http_build_query($queryParams);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200 || empty($response)) {
            $this->json([
                'success' => false,
                'message' => 'Unable to fetch autocomplete suggestions.',
                'predictions' => [],
            ]);
            return;
        }

        $data = json_decode((string)$response, true);
        $rawPredictions = $data['predictions'] ?? $data['results'] ?? [];
        $cleanPredictions = [];

        foreach ($rawPredictions as $p) {
            $placeId = $p['place_id'] ?? null;
            if (!$placeId) {
                continue;
            }

            $mainText = $p['structured_formatting']['main_text'] ?? $p['name'] ?? $p['description'] ?? '';
            $secondaryText = $p['structured_formatting']['secondary_text'] ?? '';
            $description = $p['description'] ?? trim($mainText . ($secondaryText ? ', ' . $secondaryText : ''));

            $cleanPredictions[] = [
                'place_id' => $placeId,
                'description' => $description,
                'main_text' => $mainText ?: $description,
                'secondary_text' => $secondaryText,
            ];
        }

        $this->json([
            'success' => true,
            'predictions' => $cleanPredictions,
        ]);
    }

    /**
     * Ola Maps Place Details proxy
     */
    public function placeDetails(): void
    {
        $placeId = trim((string)($this->request->query('place_id') ?? $this->request->input('place_id', '')));

        if ($placeId === '') {
            $this->json([
                'success' => false,
                'message' => 'place_id is required.',
            ], 400);
            return;
        }

        $apiKey = config('app.maps.ola_api_key') ?: getenv('OLA_MAPS_API_KEY') ?: ($_ENV['OLA_MAPS_API_KEY'] ?? '');
        if (empty($apiKey)) {
            $this->json([
                'success' => false,
                'message' => 'Ola Maps API key is not configured.',
            ], 500);
            return;
        }

        $queryParams = [
            'place_id' => $placeId,
            'api_key' => $apiKey,
            'language' => 'en',
        ];

        $url = 'https://api.olamaps.io/places/v1/details?' . http_build_query($queryParams);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200 || empty($response)) {
            $this->json([
                'success' => false,
                'message' => 'Unable to fetch place details.',
            ]);
            return;
        }

        $data = json_decode((string)$response, true);
        $result = $data['result'] ?? ($data['results'][0] ?? null);

        $location = $result['geometry']['location'] ?? null;
        if (!$location || !isset($location['lat']) || !isset($location['lng'])) {
            $this->json([
                'success' => false,
                'message' => 'Coordinates not found for this place.',
            ]);
            return;
        }

        $formattedAddress = $result['formatted_address'] ?? $result['name'] ?? '';

        $this->json([
            'success' => true,
            'place_id' => $placeId,
            'formatted_address' => $formattedAddress,
            'latitude' => round((float)$location['lat'], 7),
            'longitude' => round((float)$location['lng'], 7),
        ]);
    }

    /**
     * Geocode an address using Ola Maps Geocoding API securely from the backend
     */
    public function geocode(): void
    {
        $address = trim((string)($this->request->query('address') ?? $this->request->input('address', '')));

        if ($address === '') {
            $this->json([
                'success' => false,
                'message' => 'Please enter an address to search.'
            ], 400);
            return;
        }

        $apiKey = config('app.maps.ola_api_key') ?: getenv('OLA_MAPS_API_KEY') ?: ($_ENV['OLA_MAPS_API_KEY'] ?? '');
        if (empty($apiKey)) {
            $this->json([
                'success' => false,
                'message' => 'Unable to find coordinates for this address.'
            ], 500);
            return;
        }

        $url = 'https://api.olamaps.io/places/v1/geocode?address=' . urlencode($address) . '&api_key=' . urlencode($apiKey);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200 || empty($response)) {
            $this->json([
                'success' => false,
                'message' => 'Unable to find coordinates for this address.'
            ]);
            return;
        }

        $data = json_decode((string)$response, true);
        $location = null;

        if (!empty($data['geocodingResults'][0]['geometry']['location'])) {
            $location = $data['geocodingResults'][0]['geometry']['location'];
        } elseif (!empty($data['results'][0]['geometry']['location'])) {
            $location = $data['results'][0]['geometry']['location'];
        }

        if (!$location || !isset($location['lat']) || !isset($location['lng'])) {
            $this->json([
                'success' => false,
                'message' => 'Unable to find coordinates for this address.'
            ]);
            return;
        }

        $this->json([
            'success' => true,
            'latitude' => round((float)$location['lat'], 7),
            'longitude' => round((float)$location['lng'], 7)
        ]);
    }
}
