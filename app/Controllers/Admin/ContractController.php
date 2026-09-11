<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
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
        $contracts = $contractModel->allByTenant($orgId);

        $this->render('admin/contracts/index', [
            'pageTitle' => 'Contracts & Agreements - Secure360',
            'organisationId' => $orgId,
            'contracts' => $contracts,
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
        ], 'layouts/admin');
    }

    /**
     * Store new contract with shift and guard assignment
     */
    public function storeContract(): void
    {
        $orgId = Auth::organisationId() ?? 1;

        $customerId = (int)$this->request->input('customer_id', 0);
        $siteId = (int)$this->request->input('site_id', 0);
        $contractCode = trim((string)$this->request->input('contract_code', ''));
        $startDate = trim((string)$this->request->input('start_date', ''));
        $endDate = trim((string)$this->request->input('end_date', '')) ?: null;
        $requiredGuards = (int)$this->request->input('required_guard_count', 1);
        $extraNotes = trim((string)$this->request->input('extra_notes', '')) ?: null;

        if ($customerId <= 0 || $siteId <= 0 || $startDate === '') {
            $this->setFlash('error', 'Client, Site, and Contract Start Date are required.');
            $this->redirect('/admin/contracts/create');
            return;
        }

        if ($contractCode === '') {
            $contractCode = 'CTR-' . date('Y') . '-' . rand(100, 999);
        }

        $shiftName = trim((string)$this->request->input('shift_name', 'Regular Shift'));
        $startTime = trim((string)$this->request->input('start_time', '08:00:00'));
        $endTime = trim((string)$this->request->input('end_time', '16:00:00'));
        $guardId = (int)$this->request->input('guard_id', 0);

        $contractModel = new Contract();
        $shiftModel = new ContractShift();

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

            $shiftCode = 'SHIFT-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $shiftId = $shiftModel->create([
                'contract_id' => $contractId,
                'shift_code' => $shiftCode,
                'shift_name' => $shiftName,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => 0,
            ]);

            if ($guardId > 0) {
                $assignmentModel = new \App\Models\Assignment();
                $assignmentModel->create([
                    'contract_id' => $contractId,
                    'contract_shift_id' => $shiftId,
                    'guard_id' => $guardId,
                    'site_id' => $siteId,
                    'status' => 0,
                    'notes' => 'Allocated during contract creation',
                ]);
            }

            $contractModel->commit();
            $this->setFlash('success', "Contract '{$contractCode}' and shift schedule created successfully.");
            $this->redirect('/admin/contracts');
        } catch (\Throwable $e) {
            $contractModel->rollBack();
            $this->setFlash('error', 'Error creating contract: ' . $e->getMessage());
            $this->redirect('/admin/contracts/create');
        }
    }
}
