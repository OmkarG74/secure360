<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Guard;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SystemSetting;
use PDO;

/**
 * Subscription & Licensing Service
 * Central authoritative engine for subscription lifecycles, guard limits, billing, and invoices
 */
class SubscriptionService
{
    private Subscription $subscriptionModel;
    private Invoice $invoiceModel;
    private Guard $guardModel;
    private Organization $orgModel;
    private SystemSetting $settingModel;

    public function __construct()
    {
        $this->subscriptionModel = new Subscription();
        $this->invoiceModel = new Invoice();
        $this->guardModel = new Guard();
        $this->orgModel = new Organization();
        $this->settingModel = new SystemSetting();
    }

    /**
     * Get count of currently active/usable guards consuming subscription slots
     */
    public function countActiveGuards(int $organizationId): int
    {
        return $this->guardModel->countActiveGuards($organizationId);
    }

    /**
     * Retrieve complete subscription profile and capacity metrics for an organization
     */
    public function getSubscriptionDetails(int $organizationId): ?array
    {
        $sub = $this->subscriptionModel->findCurrentByOrganization($organizationId);
        if (!$sub) {
            return null;
        }

        $activeGuards = $this->countActiveGuards($organizationId);
        $guardLimit = (int)$sub['guard_limit'];
        $availableSlots = max(0, $guardLimit - $activeGuards);
        $usagePercentage = $guardLimit > 0 ? min(100, round(($activeGuards / $guardLimit) * 100)) : 100;
        $isLimitReached = $activeGuards >= $guardLimit;
        $isApproachingLimit = ($guardLimit - $activeGuards) <= 3 && !$isLimitReached;

        // Fetch last invoice
        $db = Database::getConnection();
        $stmtInv = $db->prepare(
            "SELECT * FROM invoices WHERE organization_id = :org_id ORDER BY id DESC LIMIT 1"
        );
        $stmtInv->execute(['org_id' => $organizationId]);
        $lastInvoice = $stmtInv->fetch() ?: null;

        $sub['active_guards'] = $activeGuards;
        $sub['available_slots'] = $availableSlots;
        $sub['usage_percentage'] = $usagePercentage;
        $sub['is_limit_reached'] = $isLimitReached;
        $sub['is_approaching_limit'] = $isApproachingLimit;
        $sub['last_invoice'] = $lastInvoice;

        return $sub;
    }

    /**
     * Check whether an organization is permitted to add or activate additional guards
     */
    public function canAddOrActivateGuard(int $organizationId, int $countToAdd = 1): array
    {
        // 1. Check organization active status
        $org = $this->orgModel->find($organizationId);
        if (!$org || (int)$org['status'] !== 0) {
            return [
                'allowed' => false,
                'message' => 'Your organisation is currently suspended. Please contact the administrator.',
                'code' => 'ORGANISATION_SUSPENDED',
                'activeGuards' => 0,
                'guardLimit' => 0,
            ];
        }

        // 2. Check subscription existence and expiry
        $sub = $this->subscriptionModel->findCurrentByOrganization($organizationId);
        if (!$sub) {
            return [
                'allowed' => false,
                'message' => 'No active subscription found for this organisation.',
                'code' => 'NO_SUBSCRIPTION',
                'activeGuards' => 0,
                'guardLimit' => 0,
            ];
        }

        $dynamicStatus = $this->subscriptionModel->calculateDynamicStatus($sub);
        if ($dynamicStatus === 'expired') {
            return [
                'allowed' => false,
                'message' => 'Subscription expired. Normal operations are restricted until renewed.',
                'code' => 'SUBSCRIPTION_EXPIRED',
                'activeGuards' => 0,
                'guardLimit' => (int)$sub['guard_limit'],
            ];
        }

        if ($dynamicStatus === 'suspended' || $dynamicStatus === 'cancelled') {
            return [
                'allowed' => false,
                'message' => 'Subscription is inactive. Please contact the administrator.',
                'code' => 'SUBSCRIPTION_INACTIVE',
                'activeGuards' => 0,
                'guardLimit' => (int)$sub['guard_limit'],
            ];
        }

        // 3. Check guard capacity limit
        $activeGuards = $this->countActiveGuards($organizationId);
        $guardLimit = (int)$sub['guard_limit'];

        if (($activeGuards + $countToAdd) > $guardLimit) {
            return [
                'allowed' => false,
                'message' => "Guard limit reached. Your organisation's subscription allows up to {$guardLimit} guards.",
                'code' => 'LIMIT_REACHED',
                'activeGuards' => $activeGuards,
                'guardLimit' => $guardLimit,
            ];
        }

        return [
            'allowed' => true,
            'message' => 'Guard creation permitted.',
            'code' => 'OK',
            'activeGuards' => $activeGuards,
            'guardLimit' => $guardLimit,
        ];
    }

    /**
     * Authoritative server-side calculation of subscription total
     */
    public function calculateTotal(int $guardLimit, float $pricePerGuard): float
    {
        return round($guardLimit * $pricePerGuard, 2);
    }

    /**
     * Provision an initial subscription and invoice during organization onboarding
     */
    public function createInitialSubscription(int $organizationId, array $data, ?int $userId = null): array
    {
        $guardLimit = max(1, (int)($data['guard_limit'] ?? 30));
        $pricePerGuard = isset($data['price_per_guard']) && (float)$data['price_per_guard'] >= 0
            ? round((float)$data['price_per_guard'], 2)
            : $this->settingModel->getDefaultPricePerGuard();

        // Authoritative server-side total amount
        $totalAmount = $this->calculateTotal($guardLimit, $pricePerGuard);

        $startDate = !empty($data['start_date']) ? $data['start_date'] : date('Y-m-d');
        $endDate = !empty($data['end_date']) ? $data['end_date'] : date('Y-m-d', strtotime('+1 year'));

        $subId = $this->subscriptionModel->create([
            'organization_id' => $organizationId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'guard_limit' => $guardLimit,
            'price_per_guard' => $pricePerGuard,
            'total_amount' => $totalAmount,
            'status' => SUBSCRIPTION_ACTIVE,
            'notes' => $data['notes'] ?? 'Initial subscription provisioned on onboarding',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        // Generate matching invoice
        $invoiceNumber = $this->invoiceModel->generateInvoiceNumber();
        $invoiceId = $this->invoiceModel->create([
            'organization_id' => $organizationId,
            'subscription_id' => $subId,
            'invoice_type' => 'Initial Subscription',
            'invoice_number' => $invoiceNumber,
            'invoice_date' => date('Y-m-d'),
            'billing_start_date' => $startDate,
            'billing_end_date' => $endDate,
            'guard_quantity' => $guardLimit,
            'price_per_guard' => $pricePerGuard,
            'subtotal' => $totalAmount,
            'total_amount' => $totalAmount,
            'status' => 'paid',
            'notes' => 'Initial subscription invoice',
        ]);

        // Record history log
        $this->subscriptionModel->recordHistory([
            'subscription_id' => $subId,
            'organization_id' => $organizationId,
            'change_type' => 'created',
            'previous_guard_limit' => null,
            'new_guard_limit' => $guardLimit,
            'previous_price_per_guard' => null,
            'new_price_per_guard' => $pricePerGuard,
            'previous_total_amount' => null,
            'new_total_amount' => $totalAmount,
            'amount_difference' => $totalAmount,
            'effective_date' => $startDate,
            'performed_by' => $userId,
            'notes' => "Subscription created with {$guardLimit} guards @ Rs. {$pricePerGuard}",
        ]);

        // Audit activity
        $this->logActivity($organizationId, $userId, 'subscription_created', 'Subscription Created', [
            'guard_limit' => $guardLimit,
            'price_per_guard' => $pricePerGuard,
            'total_amount' => $totalAmount,
            'invoice_number' => $invoiceNumber,
        ]);

        return [
            'subscription_id' => $subId,
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Renew subscription with a new billing period, guard capacity, and invoice
     */
    public function renewSubscription(int $organizationId, array $data, ?int $userId = null): array
    {
        $db = Database::getConnection();
        $currentSub = $this->subscriptionModel->findCurrentByOrganization($organizationId);

        $guardLimit = max(1, (int)($data['guard_limit'] ?? ($currentSub['guard_limit'] ?? 30)));
        $pricePerGuard = isset($data['price_per_guard']) && (float)$data['price_per_guard'] >= 0
            ? round((float)$data['price_per_guard'], 2)
            : ($currentSub ? (float)$currentSub['price_per_guard'] : $this->settingModel->getDefaultPricePerGuard());

        // Floor check: Cannot renew with fewer guards than currently active
        $activeGuards = $this->countActiveGuards($organizationId);
        if ($guardLimit < $activeGuards) {
            throw new \InvalidArgumentException(
                "Cannot renew guard limit below the current active guard count ({$activeGuards})."
            );
        }

        $startDate = !empty($data['start_date']) ? $data['start_date'] : date('Y-m-d');
        $endDate = !empty($data['end_date']) ? $data['end_date'] : date('Y-m-d', strtotime('+1 year', strtotime($startDate)));

        if (strtotime($endDate) < strtotime($startDate)) {
            throw new \InvalidArgumentException("End date cannot be prior to start date.");
        }

        $totalAmount = $this->calculateTotal($guardLimit, $pricePerGuard);

        try {
            $db->beginTransaction();

            // Mark previous subscription as inactive/completed if present
            if ($currentSub) {
                $this->subscriptionModel->update((int)$currentSub['id'], [
                    'status' => 1, // inactive / superseded
                ]);
            }

            // Create new renewal subscription record
            $newSubId = $this->subscriptionModel->create([
                'organization_id' => $organizationId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'guard_limit' => $guardLimit,
                'price_per_guard' => $pricePerGuard,
                'total_amount' => $totalAmount,
                'status' => SUBSCRIPTION_ACTIVE,
                'notes' => $data['notes'] ?? 'Subscription renewed',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // Generate renewal invoice
            $invoiceNumber = $this->invoiceModel->generateInvoiceNumber();
            $invoiceId = $this->invoiceModel->create([
                'organization_id' => $organizationId,
                'subscription_id' => $newSubId,
                'invoice_type' => 'Subscription Renewal',
                'invoice_number' => $invoiceNumber,
                'invoice_date' => date('Y-m-d'),
                'billing_start_date' => $startDate,
                'billing_end_date' => $endDate,
                'guard_quantity' => $guardLimit,
                'previous_guard_limit' => $currentSub ? (int)$currentSub['guard_limit'] : null,
                'price_per_guard' => $pricePerGuard,
                'subtotal' => $totalAmount,
                'total_amount' => $totalAmount,
                'status' => 'paid',
                'notes' => 'Subscription renewal invoice',
            ]);

            // Record history
            $this->subscriptionModel->recordHistory([
                'subscription_id' => $newSubId,
                'organization_id' => $organizationId,
                'change_type' => 'renewed',
                'previous_guard_limit' => $currentSub ? (int)$currentSub['guard_limit'] : null,
                'new_guard_limit' => $guardLimit,
                'previous_price_per_guard' => $currentSub ? (float)$currentSub['price_per_guard'] : null,
                'new_price_per_guard' => $pricePerGuard,
                'previous_total_amount' => $currentSub ? (float)$currentSub['total_amount'] : null,
                'new_total_amount' => $totalAmount,
                'amount_difference' => $totalAmount,
                'effective_date' => $startDate,
                'performed_by' => $userId,
                'notes' => "Renewed for {$guardLimit} guards until {$endDate}",
            ]);

            // Reactivate organization if it was suspended due to expiry
            $this->orgModel->update($organizationId, ['status' => 0]);

            // Audit
            $this->logActivity($organizationId, $userId, 'subscription_renewed', 'Subscription Renewed', [
                'previous_sub_id' => $currentSub['id'] ?? null,
                'new_sub_id' => $newSubId,
                'invoice_number' => $invoiceNumber,
                'guard_limit' => $guardLimit,
                'total_amount' => $totalAmount,
                'end_date' => $endDate,
            ]);

            $db->commit();

            // Notify Org Admin
            $this->notifyAdmins($organizationId, 'subscription_renewed', 'Subscription Renewed',
                "Your subscription has been renewed successfully for {$guardLimit} guards valid until " . date('d M Y', strtotime($endDate)) . "."
            );

            return [
                'subscription_id' => $newSubId,
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'total_amount' => $totalAmount,
            ];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Create a new subscription version when commercial terms are altered (Immutability & Versioning)
     * Keeps the previous subscription unchanged as historical data, marks it superseded,
     * provisions the new subscription record, and issues a new invoice against the new subscription.
     */
    public function createSubscriptionVersion(int $oldSubId, array $newTerms, ?int $userId = null): array
    {
        $db = Database::getConnection();
        $oldSub = $this->subscriptionModel->find($oldSubId);
        if (!$oldSub) {
            throw new \RuntimeException("Original subscription not found.");
        }

        $orgId = (int)$oldSub['organization_id'];
        $prevLimit = (int)$oldSub['guard_limit'];
        $prevPrice = (float)$oldSub['price_per_guard'];
        $prevTotal = (float)$oldSub['total_amount'];
        $prevEnd = (string)$oldSub['end_date'];
        $startDate = (string)$oldSub['start_date'];

        $newLimit = max(1, (int)($newTerms['guard_limit'] ?? $prevLimit));
        $newPrice = isset($newTerms['price_per_guard']) && (float)$newTerms['price_per_guard'] >= 0
            ? round((float)$newTerms['price_per_guard'], 2)
            : $prevPrice;
        $newEndDate = !empty($newTerms['end_date']) ? (string)$newTerms['end_date'] : $prevEnd;

        // Active guards floor check
        $activeGuards = $this->countActiveGuards($orgId);
        if ($newLimit < $activeGuards) {
            throw new \InvalidArgumentException(
                "Cannot reduce guard limit below current active guards roster ({$activeGuards} guards)."
            );
        }

        if (strtotime($newEndDate) < strtotime($startDate)) {
            throw new \InvalidArgumentException("Subscription expiry date cannot be prior to start date.");
        }

        $isLimitChanged = ($newLimit !== $prevLimit);
        $isPriceChanged = (abs($newPrice - $prevPrice) > 0.001);
        $isEndChanged = (strtotime($newEndDate) !== strtotime($prevEnd));

        $invoiceType = 'Subscription Update';
        $changeType = 'plan_updated';
        $notesList = [];

        if ($isLimitChanged) {
            if ($newLimit > $prevLimit) {
                $invoiceType = 'Guard Capacity Increase';
                $changeType = 'limit_increased';
                $notesList[] = "Quota increased from {$prevLimit} to {$newLimit} guards";
            } else {
                $invoiceType = 'Guard Capacity Adjustment';
                $changeType = 'limit_decreased';
                $notesList[] = "Quota adjusted from {$prevLimit} to {$newLimit} guards";
            }
        }
        if ($isEndChanged && strtotime($newEndDate) > strtotime($prevEnd)) {
            $invoiceType = $isLimitChanged ? 'Capacity & Duration Extension' : 'Subscription Duration Extension';
            $changeType = 'duration_extended';
            $notesList[] = "Duration extended to " . date('d M Y', strtotime($newEndDate));
        }
        if ($isPriceChanged) {
            if ($changeType === 'plan_updated') {
                $changeType = 'price_changed';
            }
            $notesList[] = "Rate adjusted to Rs. {$newPrice}/guard";
        }

        // Full valuation of the new subscription
        $newTotalAmount = $this->calculateTotal($newLimit, $newPrice);

        // Calculate invoice amount
        $invoiceAmount = 0.00;
        $additionalGuards = max(0, $newLimit - $prevLimit);

        if ($newLimit > $prevLimit) {
            $invoiceAmount += round($additionalGuards * $newPrice, 2);
        }
        if ($isEndChanged && strtotime($newEndDate) > strtotime($prevEnd)) {
            $prevEndDateObj = new \DateTime($prevEnd);
            $newEndDateObj = new \DateTime($newEndDate);
            $daysDiff = $prevEndDateObj->diff($newEndDateObj)->days;
            $monthsDiff = max(1, (int)ceil($daysDiff / 30.0));
            $invoiceAmount += round($monthsDiff * $newLimit * $newPrice, 2);
        }
        if ($invoiceAmount <= 0) {
            $invoiceAmount = $newTotalAmount;
        }

        try {
            $db->beginTransaction();

            // 1. Mark OLD subscription as superseded (status = 1: inactive / completed)
            $this->subscriptionModel->update($oldSubId, [
                'status' => 1, // inactive / superseded
                'updated_by' => $userId,
            ]);

            // 2. Create NEW subscription record
            $newSubId = $this->subscriptionModel->create([
                'organization_id' => $orgId,
                'start_date' => $startDate,
                'end_date' => $newEndDate,
                'guard_limit' => $newLimit,
                'price_per_guard' => $newPrice,
                'total_amount' => $newTotalAmount,
                'status' => SUBSCRIPTION_ACTIVE,
                'notes' => "Supersedes #SUB-{$oldSubId}. " . implode('; ', $notesList),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // 3. Generate NEW invoice against NEW subscription ($newSubId)
            $invoiceNumber = $this->invoiceModel->generateInvoiceNumber();
            $invoiceId = $this->invoiceModel->create([
                'organization_id' => $orgId,
                'subscription_id' => $newSubId,
                'invoice_type' => $invoiceType,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => date('Y-m-d'),
                'billing_start_date' => date('Y-m-d'),
                'billing_end_date' => $newEndDate,
                'guard_quantity' => $newLimit,
                'previous_guard_limit' => $prevLimit,
                'additional_guards' => $additionalGuards > 0 ? $additionalGuards : null,
                'price_per_guard' => $newPrice,
                'subtotal' => $invoiceAmount,
                'total_amount' => $invoiceAmount,
                'status' => 'paid',
                'notes' => "Invoice for Subscription #SUB-{$newSubId}. " . implode('; ', $notesList),
            ]);

            // 4. Record history on the new subscription
            $this->subscriptionModel->recordHistory([
                'subscription_id' => $newSubId,
                'organization_id' => $orgId,
                'change_type' => $changeType,
                'previous_guard_limit' => $prevLimit,
                'new_guard_limit' => $newLimit,
                'previous_price_per_guard' => $prevPrice,
                'new_price_per_guard' => $newPrice,
                'previous_total_amount' => $prevTotal,
                'new_total_amount' => $newTotalAmount,
                'amount_difference' => $invoiceAmount,
                'effective_date' => date('Y-m-d'),
                'performed_by' => $userId,
                'notes' => "Versioned from #SUB-{$oldSubId}. " . implode('; ', $notesList),
            ]);

            // 5. Audit log
            $this->logActivity($orgId, $userId, 'subscription_versioned', 'Subscription Version Created', [
                'previous_sub_id' => $oldSubId,
                'new_sub_id' => $newSubId,
                'invoice_number' => $invoiceNumber,
                'invoice_amount' => $invoiceAmount,
                'guard_limit' => $newLimit,
                'end_date' => $newEndDate,
            ]);

            $db->commit();

            // 6. Notify Org Admins
            $this->notifyAdmins($orgId, 'subscription_updated', 'Subscription Updated',
                "Your organisation's subscription terms have been updated (#SUB-{$newSubId}, Invoice #{$invoiceNumber})."
            );

            return [
                'old_subscription_id' => $oldSubId,
                'new_subscription_id' => $newSubId,
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'invoice_amount' => $invoiceAmount,
                'new_guard_limit' => $newLimit,
            ];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Update guard limit or price for an existing subscription
     * Strictly enforces that limit cannot be reduced below current active guards count
     */
    public function updateGuardLimit(int $organizationId, int $newLimit, ?float $newPrice = null, ?int $userId = null): array
    {
        $db = Database::getConnection();
        $sub = $this->subscriptionModel->findCurrentByOrganization($organizationId);

        if (!$sub) {
            throw new \RuntimeException("No active subscription found to modify.");
        }

        if ($newLimit <= 0) {
            throw new \InvalidArgumentException("Guard limit must be greater than zero.");
        }

        // CRITICAL BUSINESS RULE: Cannot reduce below active guard count
        $activeGuards = $this->countActiveGuards($organizationId);
        if ($newLimit < $activeGuards) {
            throw new \InvalidArgumentException(
                "Cannot reduce the guard limit below the current active guard count ({$activeGuards})."
            );
        }

        $prevLimit = (int)$sub['guard_limit'];
        $prevPrice = (float)$sub['price_per_guard'];
        $prevTotal = (float)$sub['total_amount'];

        $effectivePrice = ($newPrice !== null && $newPrice >= 0) ? round($newPrice, 2) : $prevPrice;
        $newTotal = $this->calculateTotal($newLimit, $effectivePrice);
        $amountDiff = round($newTotal - $prevTotal, 2);

        $changeType = 'limit_increased';
        if ($newLimit < $prevLimit) {
            $changeType = 'limit_decreased';
        } elseif ($newLimit === $prevLimit && $effectivePrice !== $prevPrice) {
            $changeType = 'price_changed';
        }

        try {
            $db->beginTransaction();

            $this->subscriptionModel->update((int)$sub['id'], [
                'guard_limit' => $newLimit,
                'price_per_guard' => $effectivePrice,
                'total_amount' => $newTotal,
                'updated_by' => $userId,
            ]);

            // If guard limit increased and amount difference > 0, generate invoice for incremental upgrade ONLY
            $invoiceNumber = null;
            if ($amountDiff > 0) {
                $invoiceNumber = $this->invoiceModel->generateInvoiceNumber();
                $additionalGuards = max(1, $newLimit - $prevLimit);
                $this->invoiceModel->create([
                    'organization_id' => $organizationId,
                    'subscription_id' => (int)$sub['id'],
                    'invoice_type' => 'Guard Capacity Increase',
                    'invoice_number' => $invoiceNumber,
                    'invoice_date' => date('Y-m-d'),
                    'billing_start_date' => date('Y-m-d'),
                    'billing_end_date' => $sub['end_date'],
                    'guard_quantity' => $additionalGuards,
                    'previous_guard_limit' => $prevLimit,
                    'additional_guards' => $additionalGuards,
                    'price_per_guard' => $effectivePrice,
                    'subtotal' => $amountDiff,
                    'total_amount' => $amountDiff,
                    'status' => 'paid',
                    'notes' => "Additional Guard Capacity: {$additionalGuards} guards ({$prevLimit} -> {$newLimit})",
                ]);
            }

            // Record history
            $this->subscriptionModel->recordHistory([
                'subscription_id' => (int)$sub['id'],
                'organization_id' => $organizationId,
                'change_type' => $changeType,
                'previous_guard_limit' => $prevLimit,
                'new_guard_limit' => $newLimit,
                'previous_price_per_guard' => $prevPrice,
                'new_price_per_guard' => $effectivePrice,
                'previous_total_amount' => $prevTotal,
                'new_total_amount' => $newTotal,
                'amount_difference' => $amountDiff,
                'effective_date' => date('Y-m-d'),
                'performed_by' => $userId,
                'notes' => "Guard limit adjusted: {$prevLimit} -> {$newLimit} (Additional: Rs. {$amountDiff})",
            ]);

            // Audit
            $this->logActivity($organizationId, $userId, 'subscription_limit_updated', 'Guard Limit Updated', [
                'previous_limit' => $prevLimit,
                'new_limit' => $newLimit,
                'previous_price' => $prevPrice,
                'new_price' => $effectivePrice,
                'previous_total' => $prevTotal,
                'new_total' => $newTotal,
                'amount_difference' => $amountDiff,
                'invoice_number' => $invoiceNumber,
            ]);

            $db->commit();

            // In-app & push notification dispatched after commit
            $this->notifyAdmins($organizationId, 'guard_limit_updated', 'Guard Limit Updated',
                "Your organisation's licensed guard capacity has been updated to {$newLimit} guards."
            );

            return [
                'previous_limit' => $prevLimit,
                'new_limit' => $newLimit,
                'previous_amount' => $prevTotal,
                'new_amount' => $newTotal,
                'amount_difference' => $amountDiff,
                'invoice_number' => $invoiceNumber,
            ];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Calculate additional charge for extending subscription duration
     */
    public function calculateExtensionCharge(int $subId, string $newEndDate): array
    {
        $sub = $this->subscriptionModel->find($subId);
        if (!$sub) {
            throw new \RuntimeException("Subscription not found.");
        }

        $prevEnd = new \DateTime($sub['end_date']);
        $newEnd = new \DateTime($newEndDate);

        if ($newEnd <= $prevEnd) {
            throw new \InvalidArgumentException("New end date must be after current end date (" . $prevEnd->format('d M Y') . ").");
        }

        $daysDiff = $prevEnd->diff($newEnd)->days;
        $monthsDiff = max(1, (int)ceil($daysDiff / 30.0));
        $guardLimit = (int)$sub['guard_limit'];
        $pricePerGuard = (float)$sub['price_per_guard'];
        $additionalCharge = round($monthsDiff * $guardLimit * $pricePerGuard, 2);

        return [
            'days_extended' => $daysDiff,
            'months_extended' => $monthsDiff,
            'guard_limit' => $guardLimit,
            'price_per_guard' => $pricePerGuard,
            'additional_charge' => $additionalCharge,
            'previous_end_date' => $sub['end_date'],
            'new_end_date' => $newEndDate,
        ];
    }

    /**
     * Extend subscription duration and generate invoice ONLY for the additional period
     */
    public function extendSubscriptionDuration(int $subId, string $newEndDate, ?int $userId = null): array
    {
        $db = Database::getConnection();
        $sub = $this->subscriptionModel->find($subId);
        if (!$sub) {
            throw new \RuntimeException("Subscription not found.");
        }

        $calc = $this->calculateExtensionCharge($subId, $newEndDate);
        $additionalCharge = $calc['additional_charge'];
        $prevEnd = $sub['end_date'];
        $orgId = (int)$sub['organization_id'];

        try {
            $db->beginTransaction();

            $newTotalAmount = round((float)$sub['total_amount'] + $additionalCharge, 2);
            $this->subscriptionModel->update($subId, [
                'end_date' => $newEndDate,
                'total_amount' => $newTotalAmount,
                'status' => SUBSCRIPTION_ACTIVE,
                'updated_by' => $userId,
            ]);

            $invoiceNumber = $this->invoiceModel->generateInvoiceNumber();
            $invoiceId = $this->invoiceModel->create([
                'organization_id' => $orgId,
                'subscription_id' => $subId,
                'invoice_type' => 'Subscription Extension',
                'invoice_number' => $invoiceNumber,
                'invoice_date' => date('Y-m-d'),
                'billing_start_date' => date('Y-m-d', strtotime('+1 day', strtotime($prevEnd))),
                'billing_end_date' => $newEndDate,
                'guard_quantity' => (int)$sub['guard_limit'],
                'previous_guard_limit' => (int)$sub['guard_limit'],
                'additional_guards' => 0,
                'price_per_guard' => (float)$sub['price_per_guard'],
                'subtotal' => $additionalCharge,
                'total_amount' => $additionalCharge,
                'status' => 'paid',
                'notes' => "Subscription extended from {$prevEnd} to {$newEndDate} ({$calc['months_extended']} month(s))",
            ]);

            $this->subscriptionModel->recordHistory([
                'subscription_id' => $subId,
                'organization_id' => $orgId,
                'change_type' => 'extended',
                'previous_guard_limit' => (int)$sub['guard_limit'],
                'new_guard_limit' => (int)$sub['guard_limit'],
                'previous_price_per_guard' => (float)$sub['price_per_guard'],
                'new_price_per_guard' => (float)$sub['price_per_guard'],
                'previous_total_amount' => (float)$sub['total_amount'],
                'new_total_amount' => $newTotalAmount,
                'amount_difference' => $additionalCharge,
                'effective_date' => date('Y-m-d'),
                'performed_by' => $userId,
                'notes' => "Duration extended to {$newEndDate} (Additional: Rs. {$additionalCharge})",
            ]);

            $this->logActivity($orgId, $userId, 'subscription_extended', 'Subscription Extended', [
                'subscription_id' => $subId,
                'previous_end_date' => $prevEnd,
                'new_end_date' => $newEndDate,
                'additional_charge' => $additionalCharge,
                'invoice_number' => $invoiceNumber,
            ]);

            $db->commit();

            return [
                'subscription_id' => $subId,
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'additional_charge' => $additionalCharge,
                'new_end_date' => $newEndDate,
            ];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Toggle subscription status between Active (0) and Suspended (3)
     */
    public function toggleSubscriptionStatus(int $subId, ?int $userId = null): array
    {
        $sub = $this->subscriptionModel->find($subId);
        if (!$sub) {
            throw new \RuntimeException("Subscription not found.");
        }

        $currentStatus = (int)$sub['status'];
        $newStatus = ($currentStatus === SUBSCRIPTION_SUSPENDED) ? SUBSCRIPTION_ACTIVE : SUBSCRIPTION_SUSPENDED;
        $this->subscriptionModel->update($subId, ['status' => $newStatus, 'updated_by' => $userId]);

        $action = $newStatus === SUBSCRIPTION_SUSPENDED ? 'subscription_suspended' : 'subscription_activated';
        $title = $newStatus === SUBSCRIPTION_SUSPENDED ? 'Subscription Suspended' : 'Subscription Activated';

        $this->subscriptionModel->recordHistory([
            'subscription_id' => $subId,
            'organization_id' => (int)$sub['organization_id'],
            'change_type' => $newStatus === SUBSCRIPTION_SUSPENDED ? 'suspended' : 'activated',
            'previous_guard_limit' => (int)$sub['guard_limit'],
            'new_guard_limit' => (int)$sub['guard_limit'],
            'previous_price_per_guard' => (float)$sub['price_per_guard'],
            'new_price_per_guard' => (float)$sub['price_per_guard'],
            'previous_total_amount' => (float)$sub['total_amount'],
            'new_total_amount' => (float)$sub['total_amount'],
            'amount_difference' => 0.00,
            'effective_date' => date('Y-m-d'),
            'performed_by' => $userId,
            'notes' => $title,
        ]);

        $this->logActivity((int)$sub['organization_id'], $userId, $action, $title, [
            'subscription_id' => $subId,
            'status' => $newStatus,
        ]);

        return [
            'new_status' => $newStatus,
            'status_label' => $newStatus === SUBSCRIPTION_SUSPENDED ? 'Suspended' : 'Active',
        ];
    }

    /**
     * Check global platform subscription KPIs for Superadmin Dashboard
     */
    public function getPlatformMetrics(): array
    {
        $db = Database::getConnection();

        // Active tenant organisations
        $activeOrgs = (int)$db->query(
            "SELECT COUNT(*) FROM organizations WHERE status = 0 AND deleted_at IS NULL"
        )->fetchColumn();

        // Expired subscriptions
        $expiredSubs = (int)$db->query(
            "SELECT COUNT(DISTINCT organization_id) FROM subscriptions
             WHERE end_date < CURDATE() AND status = 0"
        )->fetchColumn();

        // Expiring soon subscriptions (within threshold days)
        $threshold = $this->settingModel->getExpiringSoonDays();
        $expiringSoonSubs = (int)$db->query(
            "SELECT COUNT(DISTINCT organization_id) FROM subscriptions
             WHERE end_date >= CURDATE()
               AND end_date <= DATE_ADD(CURDATE(), INTERVAL {$threshold} DAY)
               AND status = 0"
        )->fetchColumn();

        // Total licensed guard capacity across all active subscriptions
        $totalLicensed = (int)$db->query(
            "SELECT COALESCE(SUM(s.guard_limit), 0)
             FROM subscriptions s
             JOIN (
                 SELECT organization_id, MAX(id) as max_id
                 FROM subscriptions
                 GROUP BY organization_id
             ) latest ON s.id = latest.max_id
             JOIN organizations o ON s.organization_id = o.id
             WHERE o.deleted_at IS NULL AND o.status = 0"
        )->fetchColumn();

        // Total active guards across all active organisations
        $totalActiveGuards = (int)$db->query(
            "SELECT COUNT(*) FROM guards g
             JOIN users u ON g.user_id = u.id
             JOIN organizations o ON u.organization_id = o.id
             WHERE g.status = 0 AND u.status = 0 AND o.status = 0
               AND g.deleted_at IS NULL AND u.deleted_at IS NULL AND o.deleted_at IS NULL"
        )->fetchColumn();

        return [
            'activeOrgs' => $activeOrgs,
            'expiringSoonSubs' => $expiringSoonSubs,
            'expiredSubs' => $expiredSubs,
            'totalLicensedGuards' => $totalLicensed,
            'totalActiveGuards' => $totalActiveGuards,
        ];
    }

    /**
     * Write structured audit log into activities table
     */
    public function logActivity(?int $orgId, ?int $userId, string $actionType, string $title, array $details): void
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                "INSERT INTO activities (organization_id, user_id, action_type, entity_type, title, details_json, ip_address, created_at)
                 VALUES (:org_id, :user_id, :action_type, 'subscription', :title, :details_json, :ip, NOW())"
            );
            $stmt->execute([
                'org_id' => $orgId,
                'user_id' => $userId,
                'action_type' => $actionType,
                'title' => $title,
                'details_json' => json_encode($details),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ]);
        } catch (\Throwable) {
            // Audit logging should not crash business transactions
        }
    }

    /**
     * Push in-app notification to all admins of an organisation
     */
    public function notifyAdmins(int $organizationId, string $type, string $title, string $message, ?array $data = null): void
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare(
                "SELECT id FROM users
                 WHERE organization_id = :org_id AND role_id = 2 AND deleted_at IS NULL"
            );
            $stmt->execute(['org_id' => $organizationId]);
            $adminIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $notifStmt = $db->prepare(
                "INSERT INTO notifications (organization_id, user_id, type, title, message, data_json, is_read, created_at)
                 VALUES (:org_id, :user_id, :type, :title, :message, :data_json, 0, NOW())"
            );

            foreach ($adminIds as $uId) {
                $notifStmt->execute([
                    'org_id' => $organizationId,
                    'user_id' => (int)$uId,
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'data_json' => $data ? json_encode($data) : null,
                ]);
            }

            // Attempt FCM push to admin devices
            try {
                $fcm = new FirebaseNotificationService();
                $fcm->sendToUsers(
                    array_map('intval', $adminIds),
                    $title,
                    $message,
                    array_merge($data ?? [], ['type' => $type])
                );
            } catch (\Throwable) {
                // FCM failures should not affect transaction
            }
        } catch (\Throwable) {
            // Notification failures should not break the main transaction
        }
    }
}
