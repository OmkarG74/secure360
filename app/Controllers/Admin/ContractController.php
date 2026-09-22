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
use App\Services\SubscriptionService;
use PDO;
use PDOException;

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

        if ($customerId <= 0 || $startDate === '') {
            $this->setFlash('error', 'Client and Contract Start Date are required.');
            $this->redirect('/admin/contracts/create');
            return;
        }

        // If top-level site_id not provided, derive from first assignment row or client's sites
        if ($siteId <= 0) {
            $rawAssignments = $this->request->input('assignments');
            if (is_array($rawAssignments)) {
                foreach ($rawAssignments as $row) {
                    if (is_array($row) && !empty($row['site_id']) && (int)$row['site_id'] > 0) {
                        $siteId = (int)$row['site_id'];
                        break;
                    }
                }
            }
        }
        if ($siteId <= 0) {
            $siteModel = new Site();
            $custSites = $siteModel->findByCustomer($customerId, $orgId);
            if (!empty($custSites)) {
                $siteId = (int)$custSites[0]['id'];
            }
        }

        if ($siteId <= 0) {
            $this->setFlash('error', 'The selected client has no duty sites. Please configure at least one duty site for this client first.');
            $this->redirect('/admin/contracts/create');
            return;
        }

        // Subscription limit enforcement
        $subService = new SubscriptionService();
        $sub = $subService->getSubscriptionDetails($orgId);

        if ($sub) {
            if ($sub['calculated_status'] === 'expired' || $sub['calculated_status'] === 'suspended') {
                $this->setFlash('error', "Subscription is {$sub['calculated_status']}. Cannot create contracts.");
                $this->redirect('/admin/contracts');
                return;
            }

            if ($requiredGuards > (int)$sub['guard_limit']) {
                $this->setFlash('error', "Contract requires {$requiredGuards} guard slots, which exceeds your organisation's subscription limit of {$sub['guard_limit']} guards.");
                $this->redirect('/admin/contracts/create');
                return;
            }
        }

        if ($contractCode === '') {
            $contractCode = 'CTR-' . date('Y') . '-' . rand(100, 999);
        }

        // Parse submitted dynamic assignments
        $assignments = $this->parseSubmittedAssignments($requiredGuards, $siteId);

        // Multi-tenant check: all assigned guards must belong to this organisation
        $guardModel = new Guard();
        foreach ($assignments as $row) {
            $gId = (int)$row['guard_id'];
            if ($gId > 0) {
                $guardRecord = $guardModel->findByGuardId($gId, $orgId);
                if (!$guardRecord) {
                    $this->setFlash('error', 'Invalid guard assignment: Guard does not belong to your organisation.');
                    $this->redirect('/admin/contracts/create');
                    return;
                }
            }
        }

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
                    cga.id as assignment_id, cga.guard_id, cga.site_id as assignment_site_id
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
        $siteId = (int)$this->request->input('site_id', $contract['site_id'] ?? 0);
        $startDate = trim((string)$this->request->input('start_date', $contract['start_date']));
        $endDate = trim((string)$this->request->input('end_date', '')) ?: null;
        $requiredGuards = max(1, (int)$this->request->input('required_guard_count', 1));
        $extraNotes = trim((string)$this->request->input('extra_notes', '')) ?: null;

        // If site_id not posted from top-level, derive from assignments, existing contract, or client's sites
        if ($siteId <= 0) {
            $rawAssignments = $this->request->input('assignments');
            if (is_array($rawAssignments)) {
                foreach ($rawAssignments as $row) {
                    if (is_array($row) && !empty($row['site_id']) && (int)$row['site_id'] > 0) {
                        $siteId = (int)$row['site_id'];
                        break;
                    }
                }
            }
        }
        if ($siteId <= 0 && !empty($contract['site_id'])) {
            $siteId = (int)$contract['site_id'];
        }
        if ($siteId <= 0) {
            $siteModel = new Site();
            $custSites = $siteModel->findByCustomer($customerId, $orgId);
            if (!empty($custSites)) {
                $siteId = (int)$custSites[0]['id'];
            }
        }

        // Subscription limit check
        $subService = new SubscriptionService();
        $sub = $subService->getSubscriptionDetails($orgId);
        if ($sub) {
            if ($requiredGuards > (int)$sub['guard_limit']) {
                $this->setFlash('error', "Contract requires {$requiredGuards} guard slots, which exceeds your organisation's subscription limit of {$sub['guard_limit']} guards.");
                $this->redirect("/admin/contracts/{$id}/edit");
                return;
            }
        }

        $assignments = $this->parseSubmittedAssignments($requiredGuards, $siteId);

        // Multi-tenant check
        $guardModel = new Guard();
        foreach ($assignments as $row) {
            $gId = (int)$row['guard_id'];
            if ($gId > 0) {
                $guardRecord = $guardModel->findByGuardId($gId, $orgId);
                if (!$guardRecord) {
                    $this->setFlash('error', 'Invalid guard assignment: Guard does not belong to your organisation.');
                    $this->redirect("/admin/contracts/{$id}/edit");
                    return;
                }
            }
        }

        $shiftModel = new ContractShift();
        $assignmentModel = new Assignment();
        $db = Database::getConnection();

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

            // 1. Fetch all existing shifts and assignments for this contract
            $existingShiftsStmt = $db->prepare("SELECT * FROM contract_shifts WHERE contract_id = :id");
            $existingShiftsStmt->execute(['id' => $id]);
            $existingShifts = [];
            foreach ($existingShiftsStmt->fetchAll(\PDO::FETCH_ASSOC) as $s) {
                $existingShifts[(int)$s['id']] = $s;
            }

            $existingAssignmentsStmt = $db->prepare("SELECT * FROM contract_guard_assignments WHERE contract_id = :id");
            $existingAssignmentsStmt->execute(['id' => $id]);
            $existingAssignments = [];
            foreach ($existingAssignmentsStmt->fetchAll(\PDO::FETCH_ASSOC) as $a) {
                $existingAssignments[(int)$a['id']] = $a;
            }

            // 2. Identify which assignments have foreign-key references in attendance or live locations
            $referencedAssignmentIds = [];
            if (!empty($existingAssignments)) {
                $cgaIds = array_keys($existingAssignments);
                $placeholders = implode(',', array_fill(0, count($cgaIds), '?'));

                $refAttStmt = $db->prepare("SELECT DISTINCT assignment_id FROM attendance WHERE assignment_id IN ($placeholders)");
                $refAttStmt->execute($cgaIds);
                foreach ($refAttStmt->fetchAll(\PDO::FETCH_COLUMN) as $refId) {
                    if ($refId !== null) {
                        $referencedAssignmentIds[(int)$refId] = true;
                    }
                }

                $refLiveStmt = $db->prepare("SELECT DISTINCT assignment_id FROM guard_live_locations WHERE assignment_id IN ($placeholders)");
                $refLiveStmt->execute($cgaIds);
                foreach ($refLiveStmt->fetchAll(\PDO::FETCH_COLUMN) as $refId) {
                    if ($refId !== null) {
                        $referencedAssignmentIds[(int)$refId] = true;
                    }
                }
            }

            // 3. Synchronize submitted assignments & shifts safely in place
            $activeAssignmentIds = [];
            $activeShiftIds = [];
            $assignedGuardIds = [];

            foreach ($assignments as $idx => $row) {
                $rowGuardId = (int)$row['guard_id'];
                if ($rowGuardId <= 0 || in_array($rowGuardId, $assignedGuardIds, true)) {
                    continue;
                }
                $assignedGuardIds[] = $rowGuardId;

                $subAssignId = $row['assignment_id'];
                $subShiftId = $row['shift_id'];
                $rowSiteId = $row['site_id'] > 0 ? $row['site_id'] : $siteId;
                $sName = !empty($row['shift_name']) ? $row['shift_name'] : ('Shift ' . ($idx + 1));
                $sStart = !empty($row['start_time']) ? (strlen($row['start_time']) === 5 ? $row['start_time'] . ':00' : $row['start_time']) : '08:00:00';
                $sEnd = !empty($row['end_time']) ? (strlen($row['end_time']) === 5 ? $row['end_time'] . ':00' : $row['end_time']) : '16:00:00';

                // Attempt to match with existing assignment:
                // 1. By submitted assignment_id if valid
                // 2. By guard_id if previously assigned to this contract
                $matchedAssignId = null;
                if ($subAssignId && isset($existingAssignments[$subAssignId])) {
                    $matchedAssignId = $subAssignId;
                } else {
                    foreach ($existingAssignments as $eId => $eAssign) {
                        if (!in_array($eId, $activeAssignmentIds, true) && (int)$eAssign['guard_id'] === $rowGuardId) {
                            $matchedAssignId = $eId;
                            break;
                        }
                    }
                }

                if ($matchedAssignId !== null) {
                    $targetAssign = $existingAssignments[$matchedAssignId];
                    $targetShiftId = (int)$targetAssign['contract_shift_id'];

                    if ((int)$targetAssign['guard_id'] === $rowGuardId) {
                        // Guard unchanged: Update existing assignment and shift in place
                        $updateAssignStmt = $db->prepare(
                            "UPDATE contract_guard_assignments 
                             SET site_id = :site_id, status = 0, deleted_at = NULL, updated_at = NOW() 
                             WHERE id = :id"
                        );
                        $updateAssignStmt->execute([
                            'site_id' => $rowSiteId,
                            'id' => $matchedAssignId,
                        ]);

                        if (isset($existingShifts[$targetShiftId])) {
                            $updateShiftStmt = $db->prepare(
                                "UPDATE contract_shifts 
                                 SET shift_name = :shift_name, start_time = :start_time, end_time = :end_time, status = 0, deleted_at = NULL, updated_at = NOW() 
                                 WHERE id = :id"
                            );
                            $updateShiftStmt->execute([
                                'shift_name' => $sName,
                                'start_time' => $sStart,
                                'end_time' => $sEnd,
                                'id' => $targetShiftId,
                            ]);
                            $activeShiftIds[] = $targetShiftId;
                        }

                        $activeAssignmentIds[] = $matchedAssignId;
                    } else {
                        // Guard changed on this row:
                        if (isset($referencedAssignmentIds[$matchedAssignId])) {
                            // Has historical attendance: deactivate old assignment to preserve FK integrity
                            $deactStmt = $db->prepare(
                                "UPDATE contract_guard_assignments 
                                 SET status = 1, deleted_at = NOW(), updated_at = NOW() 
                                 WHERE id = :id"
                            );
                            $deactStmt->execute(['id' => $matchedAssignId]);

                            // Create a new shift for the new guard
                            $shiftCode = 'SHIFT-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
                            $newShiftId = (int)$shiftModel->create([
                                'contract_id' => $id,
                                'shift_code' => $shiftCode,
                                'shift_name' => $sName,
                                'start_time' => $sStart,
                                'end_time' => $sEnd,
                                'status' => 0,
                            ]);
                            $activeShiftIds[] = $newShiftId;

                            // Create a new assignment for the new guard
                            $newAssignId = (int)$assignmentModel->create([
                                'contract_id' => $id,
                                'contract_shift_id' => $newShiftId,
                                'guard_id' => $rowGuardId,
                                'site_id' => $rowSiteId,
                                'status' => 0,
                                'notes' => 'Allocated during contract update',
                            ]);
                            $activeAssignmentIds[] = $newAssignId;
                        } else {
                            // No historical attendance: safely update in place
                            $updateAssignStmt = $db->prepare(
                                "UPDATE contract_guard_assignments 
                                 SET guard_id = :guard_id, site_id = :site_id, status = 0, deleted_at = NULL, updated_at = NOW() 
                                 WHERE id = :id"
                            );
                            $updateAssignStmt->execute([
                                'guard_id' => $rowGuardId,
                                'site_id' => $rowSiteId,
                                'id' => $matchedAssignId,
                            ]);

                            if (isset($existingShifts[$targetShiftId])) {
                                $updateShiftStmt = $db->prepare(
                                    "UPDATE contract_shifts 
                                     SET shift_name = :shift_name, start_time = :start_time, end_time = :end_time, status = 0, deleted_at = NULL, updated_at = NOW() 
                                     WHERE id = :id"
                                );
                                $updateShiftStmt->execute([
                                    'shift_name' => $sName,
                                    'start_time' => $sStart,
                                    'end_time' => $sEnd,
                                    'id' => $targetShiftId,
                                ]);
                                $activeShiftIds[] = $targetShiftId;
                            }

                            $activeAssignmentIds[] = $matchedAssignId;
                        }
                    }
                } else {
                    // New assignment row:
                    // Check if submitted shift_id exists and can be reused
                    $shiftId = null;
                    if ($subShiftId && isset($existingShifts[$subShiftId]) && !in_array($subShiftId, $activeShiftIds, true)) {
                        $shiftId = $subShiftId;
                        $updateShiftStmt = $db->prepare(
                            "UPDATE contract_shifts 
                             SET shift_name = :shift_name, start_time = :start_time, end_time = :end_time, status = 0, deleted_at = NULL, updated_at = NOW() 
                             WHERE id = :id"
                        );
                        $updateShiftStmt->execute([
                            'shift_name' => $sName,
                            'start_time' => $sStart,
                            'end_time' => $sEnd,
                            'id' => $shiftId,
                        ]);
                    } else {
                        $shiftCode = 'SHIFT-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
                        $shiftId = (int)$shiftModel->create([
                            'contract_id' => $id,
                            'shift_code' => $shiftCode,
                            'shift_name' => $sName,
                            'start_time' => $sStart,
                            'end_time' => $sEnd,
                            'status' => 0,
                        ]);
                    }
                    $activeShiftIds[] = $shiftId;

                    $newAssignId = (int)$assignmentModel->create([
                        'contract_id' => $id,
                        'contract_shift_id' => $shiftId,
                        'guard_id' => $rowGuardId,
                        'site_id' => $rowSiteId,
                        'status' => 0,
                        'notes' => 'Allocated during contract update',
                    ]);
                    $activeAssignmentIds[] = $newAssignId;
                }
            }

            // 4. Handle removed assignments safely
            foreach ($existingAssignments as $oldAssignId => $oldAssign) {
                if (!in_array($oldAssignId, $activeAssignmentIds, true)) {
                    if (isset($referencedAssignmentIds[$oldAssignId])) {
                        // Has attendance references: DO NOT DELETE. Mark inactive to preserve FK integrity.
                        $deactStmt = $db->prepare(
                            "UPDATE contract_guard_assignments 
                             SET status = 1, deleted_at = NOW(), updated_at = NOW() 
                             WHERE id = :id"
                        );
                        $deactStmt->execute(['id' => $oldAssignId]);
                    } else {
                        // No attendance references: safe to delete
                        $db->prepare("DELETE FROM contract_guard_assignments WHERE id = :id")->execute(['id' => $oldAssignId]);
                    }
                }
            }

            // 5. Clean up unreferenced shifts safely
            foreach ($existingShifts as $oldShiftId => $oldShift) {
                if (!in_array($oldShiftId, $activeShiftIds, true)) {
                    $countStmt = $db->prepare("SELECT COUNT(*) FROM contract_guard_assignments WHERE contract_shift_id = :id");
                    $countStmt->execute(['id' => $oldShiftId]);
                    $refCount = (int)$countStmt->fetchColumn();

                    if ($refCount > 0) {
                        // Still referenced by an assignment: keep but mark inactive
                        $db->prepare("UPDATE contract_shifts SET status = 1, deleted_at = NOW(), updated_at = NOW() WHERE id = :id")->execute(['id' => $oldShiftId]);
                    } else {
                        // Unreferenced: safe to delete
                        $db->prepare("DELETE FROM contract_shifts WHERE id = :id")->execute(['id' => $oldShiftId]);
                    }
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
                        $assignedSiteId = !empty($row['site_id']) ? (int)$row['site_id'] : $defaultSiteId;
                        $assignments[] = [
                            'assignment_id' => !empty($row['assignment_id']) ? (int)$row['assignment_id'] : null,
                            'shift_id' => !empty($row['shift_id']) ? (int)$row['shift_id'] : null,
                            'guard_id' => $guardId,
                            'shift_name' => trim((string)($row['shift_name'] ?? 'Day Patrol')),
                            'start_time' => trim((string)($row['start_time'] ?? '08:00')),
                            'end_time' => trim((string)($row['end_time'] ?? '16:00')),
                            'site_id' => $assignedSiteId,
                        ];
                    }
                }
            }
        }

        // Limit to required guards maximum count
        return array_slice($assignments, 0, $requiredGuards);
    }
}
