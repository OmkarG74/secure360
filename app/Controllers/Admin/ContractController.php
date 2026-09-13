<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Assignment;
use App\Models\Contract;
use App\Models\ContractShift;
use App\Models\Customer;
use App\Models\Guard;
use App\Models\Site;

/**
 * Contract Operations Controller
 * Connected to secure360_v2 `contracts` table joined with customers, sites, shifts, assignments
 */
class ContractController extends Controller
{
    /**
     * Display contracts list
     */
    public function index(Request $request = null, Response $response = null): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $contractModel = new Contract();
        $allContracts = $contractModel->allByTenant($orgId);

        $statusFilter = $this->request->query('status', 'all');
        $searchQuery = trim((string)$this->request->query('search', ''));

        $totalCount = count($allContracts);
        $activeCount = 0;
        $inactiveCount = 0;

        foreach ($allContracts as $c) {
            if ((int)$c['status'] === 0) {
                $activeCount++;
            } else {
                $inactiveCount++;
            }
        }

        $filtered = array_filter($allContracts, function ($c) use ($statusFilter, $searchQuery) {
            if ($statusFilter === 'active' && (int)$c['status'] !== 0) {
                return false;
            }
            if ($statusFilter === 'inactive' && (int)$c['status'] === 0) {
                return false;
            }
            if ($searchQuery !== '') {
                $query = strtolower($searchQuery);
                $codeMatches = str_contains(strtolower($c['contract_code'] ?? ''), $query);
                $custMatches = str_contains(strtolower($c['customer_name'] ?? ''), $query);
                $clientCodeMatches = str_contains(strtolower($c['client_code'] ?? ''), $query);
                $siteMatches = str_contains(strtolower($c['site_name'] ?? ''), $query);
                if (!$codeMatches && !$custMatches && !$clientCodeMatches && !$siteMatches) {
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
        $pagedContracts = array_slice($filteredValues, $offset, $pageSize);

        $this->render('admin/contracts/index', [
            'pageTitle' => 'Contracts & Agreements - Secure360',
            'organisationId' => $orgId,
            'contracts' => $pagedContracts,
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
     * Form to create a new contract
     */
    public function createForm(Request $request = null, Response $response = null): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $customerModel = new Customer();
        $siteModel = new Site();
        $guardModel = new Guard();

        $customers = $customerModel->allByTenant($orgId);
        $sites = $siteModel->allByTenant($orgId);
        $guards = $guardModel->getGuardsWithDetails($orgId);

        $contractCode = 'CTR-' . date('Y') . '-' . rand(100, 999);

        $this->render('admin/contracts/create', [
            'pageTitle' => 'New Service Contract - Secure360',
            'organisationId' => $orgId,
            'customers' => $customers,
            'sites' => $sites,
            'guards' => $guards,
            'contractCode' => $contractCode,
            'existingAssignments' => [],
        ], 'layouts/admin');
    }

    /**
     * Store new contract with shift and guard assignments
     */
    public function storeContract(): void
    {
        $orgId = Auth::organisationId() ?? 1;

        $customerId = (int)$this->request->input('customer_id', 0);
        $siteId = (int)$this->request->input('site_id', 0);
        $contractCode = trim((string)$this->request->input('contract_code', ''));
        $startDate = trim((string)$this->request->input('start_date', ''));
        $endDate = trim((string)$this->request->input('end_date', '')) ?: null;
        $requiredGuards = max(1, (int)$this->request->input('required_guard_count', 1));
        $extraNotes = trim((string)$this->request->input('extra_notes', '')) ?: null;

        if ($customerId <= 0 || $siteId <= 0 || $startDate === '') {
            $this->setFlash('error', 'Client, Site, and Contract Start Date are required.');
            $this->redirect('/admin/contracts/create');
            return;
        }

        if ($contractCode === '') {
            $contractCode = 'CTR-' . date('Y') . '-' . rand(100, 999);
        }

        // Parse submitted dynamic assignments
        $assignments = $this->parseSubmittedAssignments($requiredGuards, $siteId);

        $contractModel = new Contract();
        $shiftModel = new ContractShift();
        $assignmentModel = new Assignment();

        try {
            $contractModel->beginTransaction();

            $contractId = $contractModel->create([
                'organization_id' => $orgId,
                'customer_id' => $customerId,
                'site_id' => $siteId,
                'contract_code' => $contractCode,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'required_guard_count' => $requiredGuards,
                'extra_notes' => $extraNotes,
                'status' => 0,
            ]);

            $assignedGuards = [];
            foreach ($assignments as $idx => $row) {
                $shiftCode = 'SHIFT-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
                $sName = !empty($row['shift_name']) ? $row['shift_name'] : ('Shift ' . ($idx + 1));
                $sStart = !empty($row['start_time']) ? (strlen($row['start_time']) === 5 ? $row['start_time'] . ':00' : $row['start_time']) : '08:00:00';
                $sEnd = !empty($row['end_time']) ? (strlen($row['end_time']) === 5 ? $row['end_time'] . ':00' : $row['end_time']) : '16:00:00';
                $rowSiteId = $row['site_id'] > 0 ? $row['site_id'] : $siteId;
                $rowGuardId = (int)$row['guard_id'];

                $shiftId = $shiftModel->create([
                    'contract_id' => $contractId,
                    'shift_code' => $shiftCode,
                    'shift_name' => $sName,
                    'start_time' => $sStart,
                    'end_time' => $sEnd,
                    'status' => 0,
                ]);

                if ($rowGuardId > 0 && !in_array($rowGuardId, $assignedGuards, true)) {
                    $assignedGuards[] = $rowGuardId;
                    $assignmentModel->create([
                        'contract_id' => $contractId,
                        'contract_shift_id' => $shiftId,
                        'guard_id' => $rowGuardId,
                        'site_id' => $rowSiteId,
                        'status' => 0,
                        'notes' => 'Allocated during contract creation',
                    ]);
                }
            }

            $contractModel->commit();
            $this->setFlash('success', "Contract '{$contractCode}' with {$requiredGuards} guard assignment slot(s) created successfully.");
            $this->redirect('/admin/contracts');
        } catch (\Throwable $e) {
            $contractModel->rollBack();
            $this->setFlash('error', 'Error creating contract: ' . $e->getMessage());
            $this->redirect('/admin/contracts/create');
        }
    }

    /**
     * Form to edit an existing contract
     */
    public function editForm(Request $request = null, Response $response = null, array $params = []): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $id = (int)($params['id'] ?? $this->request->query('id', 0));

        $contractModel = new Contract();
        $contract = $contractModel->findByTenant($id, $orgId);

        if (!$contract) {
            $this->setFlash('error', 'Contract not found.');
            $this->redirect('/admin/contracts');
            return;
        }

        $customerModel = new Customer();
        $siteModel = new Site();
        $guardModel = new Guard();

        $customers = $customerModel->allByTenant($orgId);
        $sites = $siteModel->allByTenant($orgId);
        $guards = $guardModel->getGuardsWithDetails($orgId);

        // Fetch existing shifts & assignments
        $stmt = Database::getConnection()->prepare(
            "SELECT cs.id as shift_id, cs.shift_name, cs.start_time, cs.end_time,
                    cga.guard_id, cga.site_id as assignment_site_id
             FROM contract_shifts cs
             LEFT JOIN contract_guard_assignments cga ON cga.contract_shift_id = cs.id AND cga.deleted_at IS NULL AND cga.status = 0
             WHERE cs.contract_id = :contract_id AND cs.deleted_at IS NULL
             ORDER BY cs.id ASC"
        );
        $stmt->execute(['contract_id' => $id]);
        $existingAssignments = $stmt->fetchAll() ?: [];

        $this->render('admin/contracts/edit', [
            'pageTitle' => 'Edit Contract (' . $contract['contract_code'] . ') - Secure360',
            'organisationId' => $orgId,
            'contract' => $contract,
            'customers' => $customers,
            'sites' => $sites,
            'guards' => $guards,
            'existingAssignments' => $existingAssignments,
        ], 'layouts/admin');
    }

    /**
     * Update existing contract
     */
    public function updateContract(Request $request = null, Response $response = null, array $params = []): void
    {
        $orgId = Auth::organisationId() ?? 1;
        $id = (int)($params['id'] ?? $this->request->query('id', 0));

        $contractModel = new Contract();
        $contract = $contractModel->findByTenant($id, $orgId);

        if (!$contract) {
            $this->setFlash('error', 'Contract not found.');
            $this->redirect('/admin/contracts');
            return;
        }

        $customerId = (int)$this->request->input('customer_id', $contract['customer_id']);
        $siteId = (int)$this->request->input('site_id', $contract['site_id']);
        $startDate = trim((string)$this->request->input('start_date', $contract['start_date']));
        $endDate = trim((string)$this->request->input('end_date', '')) ?: null;
        $requiredGuards = max(1, (int)$this->request->input('required_guard_count', 1));
        $extraNotes = trim((string)$this->request->input('extra_notes', '')) ?: null;

        $assignments = $this->parseSubmittedAssignments($requiredGuards, $siteId);

        $shiftModel = new ContractShift();
        $assignmentModel = new Assignment();

        try {
            $contractModel->beginTransaction();

            $contractModel->update($id, [
                'customer_id' => $customerId,
                'site_id' => $siteId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'required_guard_count' => $requiredGuards,
                'extra_notes' => $extraNotes,
            ]);

            // Soft-delete or clear previous assignments for this contract to re-sync
            $db = Database::getConnection();
            $db->prepare("DELETE FROM contract_guard_assignments WHERE contract_id = :id")->execute(['id' => $id]);
            $db->prepare("DELETE FROM contract_shifts WHERE contract_id = :id")->execute(['id' => $id]);

            $assignedGuards = [];
            foreach ($assignments as $idx => $row) {
                $shiftCode = 'SHIFT-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
                $sName = !empty($row['shift_name']) ? $row['shift_name'] : ('Shift ' . ($idx + 1));
                $sStart = !empty($row['start_time']) ? (strlen($row['start_time']) === 5 ? $row['start_time'] . ':00' : $row['start_time']) : '08:00:00';
                $sEnd = !empty($row['end_time']) ? (strlen($row['end_time']) === 5 ? $row['end_time'] . ':00' : $row['end_time']) : '16:00:00';
                $rowSiteId = $row['site_id'] > 0 ? $row['site_id'] : $siteId;
                $rowGuardId = (int)$row['guard_id'];

                $shiftId = $shiftModel->create([
                    'contract_id' => $id,
                    'shift_code' => $shiftCode,
                    'shift_name' => $sName,
                    'start_time' => $sStart,
                    'end_time' => $sEnd,
                    'status' => 0,
                ]);

                if ($rowGuardId > 0 && !in_array($rowGuardId, $assignedGuards, true)) {
                    $assignedGuards[] = $rowGuardId;
                    $assignmentModel->create([
                        'contract_id' => $id,
                        'contract_shift_id' => $shiftId,
                        'guard_id' => $rowGuardId,
                        'site_id' => $rowSiteId,
                        'status' => 0,
                        'notes' => 'Allocated during contract update',
                    ]);
                }
            }

            $contractModel->commit();
            $this->setFlash('success', "Contract updated successfully with {$requiredGuards} guard assignment slot(s).");
            $this->redirect('/admin/contracts');
        } catch (\Throwable $e) {
            $contractModel->rollBack();
            $this->setFlash('error', 'Error updating contract: ' . $e->getMessage());
            $this->redirect('/admin/contracts/' . $id . '/edit');
        }
    }

    /**
     * Helper to safely extract and normalize assignment rows up to $requiredGuards
     */
    private function parseSubmittedAssignments(int $requiredGuards, int $defaultSiteId): array
    {
        $rawAssignments = $this->request->input('assignments');
        $assignments = [];

        if (is_array($rawAssignments)) {
            foreach ($rawAssignments as $row) {
                if (is_array($row)) {
                    $guardId = (int)($row['guard_id'] ?? 0);
                    if ($guardId > 0) {
                        $assignments[] = [
                            'guard_id' => $guardId,
                            'shift_name' => trim((string)($row['shift_name'] ?? 'Day Patrol')),
                            'start_time' => trim((string)($row['start_time'] ?? '08:00')),
                            'end_time' => trim((string)($row['end_time'] ?? '16:00')),
                            'site_id' => (int)($row['site_id'] ?? $defaultSiteId),
                        ];
                    }
                }
            }
        }

        // Limit to required guards maximum count
        return array_slice($assignments, 0, $requiredGuards);
    }
}
