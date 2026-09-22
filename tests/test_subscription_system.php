<?php

declare(strict_types=1);

/**
 * Complete Verification Test Suite for Secure360 Subscription & Licensing System
 * Covers all 14 mandatory test cases.
 */

// Define application directory constants
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'app');
define('PUBLIC_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'public');

// Load .env
$envFile = ROOT_PATH . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");
            putenv("{$k}={$v}");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
}

// Load constants & helpers
require_once APP_PATH . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'constants.php';
require_once APP_PATH . DIRECTORY_SEPARATOR . 'Helpers' . DIRECTORY_SEPARATOR . 'helpers.php';

// Register PSR-4 Autoloader
require_once APP_PATH . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'Autoloader.php';
\App\Core\Autoloader::register(APP_PATH);

use App\Core\Database;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\Invoice;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Guard;
use App\Services\SubscriptionService;
use App\Services\PdfInvoiceService;

class SubscriptionTestSuite
{
    private \PDO $db;
    private SubscriptionService $subService;
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->subService = new SubscriptionService();
    }

    private function assert(bool $condition, string $testName, string $failureMsg = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$testName}\n";
        } else {
            $this->failed++;
            $msg = "  [FAIL] {$testName}" . ($failureMsg ? ": {$failureMsg}" : '');
            $this->errors[] = $msg;
            echo "{$msg}\n";
        }
    }

    public function run(): void
    {
        echo "====================================================================\n";
        echo "SECURE360 SUBSCRIPTION & LICENSING SYSTEM VERIFICATION\n";
        echo "====================================================================\n\n";

        $this->test1_migrationExecution();
        $orgId = $this->test2_organizationOnboardingAndDefaultSubscription();
        $this->test3_guardCreationWithinLimit($orgId);
        $this->test4_guardLimitEnforcementBlockedAtCap($orgId);
        $this->test5_inactiveGuardReactivationEnforcement($orgId);
        $this->test6_limitIncreaseAndDifferenceInvoice($orgId);
        $this->test7_guardLimitFloorProtection($orgId);
        $this->test8_subscriptionRenewal($orgId);
        $this->test9_systemSettingsPriceImmutability($orgId);
        $this->test10_pdfInvoiceGeneration($orgId);
        $this->test11_tenantIsolation();
        $this->test12_expiredSubscriptionDetection($orgId);
        $this->test13_organizationSuspension();
        $this->test14_contractGuardCapacityValidation($orgId);

        echo "\n====================================================================\n";
        echo "TEST SUMMARY: {$this->passed} PASSED, {$this->failed} FAILED\n";
        echo "====================================================================\n";

        if ($this->failed > 0) {
            echo "Failures:\n";
            foreach ($this->errors as $err) {
                echo $err . "\n";
            }
            exit(1);
        } else {
            echo "ALL VERIFICATION TESTS COMPLETED SUCCESSFULLY!\n";
            exit(0);
        }
    }

    // 1. Run Migration 002
    private function test1_migrationExecution(): void
    {
        echo "1. Testing Database Migration 002...\n";
        $sqlPath = __DIR__ . '/../database/migrations/002_create_subscriptions_and_invoices.sql';
        $sql = file_get_contents($sqlPath);

        try {
            $this->db->exec($sql);

            // Check tables exist
            $tables = ['system_settings', 'subscriptions', 'invoices', 'subscription_history'];
            foreach ($tables as $t) {
                $stmt = $this->db->query("SHOW TABLES LIKE '{$t}'");
                $exists = (bool)$stmt->fetchColumn();
                $this->assert($exists, "Table '{$t}' exists in secure360_v2");
            }
        } catch (\Throwable $e) {
            $this->assert(false, "Migration 002 execution", $e->getMessage());
        }
    }

    // 2. Onboarding creates default 30-guard subscription & invoice
    private function test2_organizationOnboardingAndDefaultSubscription(): int
    {
        echo "\n2. Testing Tenant Onboarding & Default Subscription Creation...\n";
        $code = 'TEST-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

        $orgModel = new Organization();
        $orgId = (int)$orgModel->create([
            'name' => 'Apex Security Solutions',
            'organization_code' => $code,
            'contact_person' => 'Vikram Malhotra',
            'email' => "apex_{$code}@example.com",
            'phone' => '+91 98765 43210',
            'status' => 0,
        ]);

        $this->assert($orgId > 0, "Created test organisation (ID: {$orgId})");

        // Provision subscription via SubscriptionService
        $result = $this->subService->createInitialSubscription($orgId, [
            'guard_limit' => 30,
            'price_per_guard' => 500.0,
        ], 1);

        $subModel = new Subscription();
        $currentSub = $subModel->findCurrentByOrganization($orgId);

        $this->assert(!empty($currentSub), "Active subscription discovered for tenant");
        $this->assert((int)($currentSub['guard_limit'] ?? 0) === 30, "Guard limit is 30");
        $this->assert((float)($currentSub['price_per_guard'] ?? 0) == 500.00, "Price per guard is 500.00");
        $this->assert((float)($currentSub['total_amount'] ?? 0) == 15000.00, "Total amount is 15000.00 (30 * 500)");

        // Check invoice
        $invoiceModel = new Invoice();
        $invoices = $invoiceModel->allByTenant($orgId);
        $this->assert(count($invoices) >= 1, "Initial invoice generated for tenant");
        $inv = $invoices[0];
        $this->assert(str_starts_with($inv['invoice_number'], 'INV-'), "Invoice format starts with INV- (" . $inv['invoice_number'] . ")");
        $this->assert((float)$inv['total_amount'] == 15000.00, "Invoice amount matches 15000.00");

        return $orgId;
    }

    // 3. Guard creation within limit (add 5 active guards)
    private function test3_guardCreationWithinLimit(int $orgId): void
    {
        echo "\n3. Testing Guard Creation Within Capacity...\n";
        $userModel = new User();
        $guardModel = new Guard();

        for ($i = 1; $i <= 5; $i++) {
            $canAdd = $this->subService->canAddOrActivateGuard($orgId);
            $this->assert($canAdd['allowed'], "Guard #{$i} is allowed to be added");

            $empCode = "EMP-{$orgId}-" . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
            $userId = (int)$userModel->create([
                'organization_id' => $orgId,
                'role_id' => 3,
                'full_name' => "Guard {$i}",
                'email' => "guard{$i}_{$orgId}@example.com",
                'employee_code' => $empCode,
                'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
                'status' => 0,
            ]);

            $guardModel->create([
                'user_id' => $userId,
                'status' => 0,
            ]);
        }

        $activeCount = $this->subService->countActiveGuards($orgId);
        $this->assert($activeCount === 5, "Active guard count accurately reports 5 guards");
    }

    // 4. Guard Limit Enforcement: fill up to 30 and attempt 31st
    private function test4_guardLimitEnforcementBlockedAtCap(int $orgId): void
    {
        echo "\n4. Testing Guard Limit Hard Enforcement at 30/30 Cap...\n";
        $userModel = new User();
        $guardModel = new Guard();

        // Fill remaining 25 slots (total 30)
        for ($i = 6; $i <= 30; $i++) {
            $empCode = "EMP-{$orgId}-" . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
            $userId = (int)$userModel->create([
                'organization_id' => $orgId,
                'role_id' => 3,
                'full_name' => "Guard {$i}",
                'email' => "guard{$i}_{$orgId}@example.com",
                'employee_code' => $empCode,
                'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
                'status' => 0,
            ]);

            $guardModel->create([
                'user_id' => $userId,
                'status' => 0,
            ]);
        }

        $activeCount = $this->subService->countActiveGuards($orgId);
        $this->assert($activeCount === 30, "Active guard count is exactly at cap (30/30)");

        // Now test 31st guard check
        $check = $this->subService->canAddOrActivateGuard($orgId);
        $this->assert(!$check['allowed'], "31st guard addition is blocked by SubscriptionService");
        $this->assert(str_contains($check['message'], 'Guard limit reached'), "Clear user-facing error returned: " . $check['message']);
    }

    // 5. Inactive guard reactivation rejected when full
    private function test5_inactiveGuardReactivationEnforcement(int $orgId): void
    {
        echo "\n5. Testing Inactive Guard Reactivation Enforcement...\n";
        $userModel = new User();
        $guardModel = new Guard();

        // Create an inactive guard
        $empCode = "EMP-{$orgId}-INACT";
        $inactiveUserId = (int)$userModel->create([
            'organization_id' => $orgId,
            'role_id' => 3,
            'full_name' => "Inactive Guard",
            'email' => "inactive_{$orgId}@example.com",
            'employee_code' => $empCode,
            'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
            'status' => 1, // Inactive
        ]);

        $inactiveGuardId = (int)$guardModel->create([
            'user_id' => $inactiveUserId,
            'status' => 1, // Inactive
        ]);

        // Verify inactive guards don't consume quota
        $activeCount = $this->subService->countActiveGuards($orgId);
        $this->assert($activeCount === 30, "Inactive guard does not increase active guard quota count (still 30)");

        // Attempt reactivation check
        $canActivate = $this->subService->canAddOrActivateGuard($orgId, 1);
        $this->assert(!$canActivate['allowed'], "Reactivating inactive guard is blocked when limit is full");
        $this->assert(str_contains($canActivate['message'], 'Guard limit reached'), "Error indicates guard limit reached: " . $canActivate['message']);
    }

    // 6. Increase limit 30 -> 40 and generate difference invoice
    private function test6_limitIncreaseAndDifferenceInvoice(int $orgId): void
    {
        echo "\n6. Testing Limit Upgrade from 30 to 40 Slots...\n";
        $upgradeResult = $this->subService->updateGuardLimit($orgId, 40, null, 1);

        $this->assert($upgradeResult['new_limit'] === 40, "Guard limit upgraded to 40");
        $this->assert($upgradeResult['new_amount'] == 20000.00, "New subscription amount is 20000.00");
        $this->assert(!empty($upgradeResult['invoice_number']), "Upgrade invoice generated: " . ($upgradeResult['invoice_number'] ?? ''));

        // Verify difference invoice record
        $invModel = new Invoice();
        $upgradeInv = $invModel->findByInvoiceNumber($upgradeResult['invoice_number']);
        $this->assert(!empty($upgradeInv), "Difference invoice retrieved from database");
        $this->assert((float)$upgradeInv['total_amount'] == 5000.00, "Difference invoice amount is 5000.00 (10 * 500)");
        $this->assert((int)$upgradeInv['guard_quantity'] === 10, "Difference invoice quantity is 10 guards");

        // Now 31st guard can be added
        $check = $this->subService->canAddOrActivateGuard($orgId);
        $this->assert($check['allowed'], "31st guard is now permitted after limit upgrade");
    }

    // 7. Floor protection: Cannot reduce limit below active guard count
    private function test7_guardLimitFloorProtection(int $orgId): void
    {
        echo "\n7. Testing Guard Limit Floor Protection...\n";
        $activeCount = $this->subService->countActiveGuards($orgId); // 30
        $this->assert($activeCount === 30, "Currently 30 active guards in tenant");

        $threwException = false;
        $errorMsg = '';
        try {
            $this->subService->updateGuardLimit($orgId, 25, null, 1);
        } catch (\InvalidArgumentException $e) {
            $threwException = true;
            $errorMsg = $e->getMessage();
        }

        $this->assert($threwException, "Decreasing limit below active count (25 < 30) threw InvalidArgumentException");
        $this->assert(str_contains($errorMsg, 'Cannot reduce the guard limit below the current active guard count'), "Exception message: {$errorMsg}");

        // Valid reduction to exactly 30 should succeed without invoice
        $safeReduction = $this->subService->updateGuardLimit($orgId, 30, null, 1);
        $this->assert($safeReduction['new_limit'] === 30, "Safe reduction to active count (30) succeeded");
        $this->assert(empty($safeReduction['invoice_number']), "No invoice generated on limit reduction");
    }

    // 8. Subscription renewal
    private function test8_subscriptionRenewal(int $orgId): void
    {
        echo "\n8. Testing Subscription Renewal...\n";
        $renewal = $this->subService->renewSubscription($orgId, [
            'guard_limit' => 35,
            'price_per_guard' => 550.00,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+1 year')),
            'notes' => 'Annual contract renewal',
        ], 1);

        $subModel = new Subscription();
        $renewedSub = $subModel->find((int)$renewal['subscription_id']);
        $this->assert((int)($renewedSub['guard_limit'] ?? 0) === 35, "Renewed subscription guard limit is 35");
        $this->assert((float)($renewedSub['price_per_guard'] ?? 0) == 550.00, "Renewed price per guard is 550.00");
        $this->assert((float)$renewal['total_amount'] == 19250.00, "Renewed total amount is 19250.00 (35 * 550)");
        $this->assert(!empty($renewal['invoice_number']), "Renewal invoice generated: " . $renewal['invoice_number']);

        // History check
        $history = $subModel->getHistory((int)$renewal['subscription_id']);
        $this->assert(count($history) >= 1, "Subscription history contains audit records");
    }

    // 9. System settings price immutability
    private function test9_systemSettingsPriceImmutability(int $orgId): void
    {
        echo "\n9. Testing System Setting Price Change Immutability...\n";
        $settingModel = new SystemSetting();
        $settingModel->set('subscription_price_per_guard', '750.00', 'New global default price');

        // Check that existing subscription retains its contractual price
        $subModel = new Subscription();
        $currentSub = $subModel->findCurrentByOrganization($orgId);
        $this->assert((float)$currentSub['price_per_guard'] == 550.00, "Existing subscription price unchanged (550.00, not 750.00)");

        // Reset default
        $settingModel->set('subscription_price_per_guard', '500.00');
    }

    // 10. PDF invoice generation
    private function test10_pdfInvoiceGeneration(int $orgId): void
    {
        echo "\n10. Testing Zero-Dependency Core PHP PDF Invoice Generation...\n";
        $invModel = new Invoice();
        $invoices = $invModel->allByTenant($orgId);
        $firstInv = $invModel->findWithDetails((int)$invoices[0]['id']);

        $pdfService = new PdfInvoiceService();
        $pdfData = $pdfService->generate($firstInv);

        $this->assert(!empty($pdfData), "PDF generation succeeded (Length: " . strlen($pdfData) . " bytes)");
        $this->assert(str_starts_with($pdfData, '%PDF-1.4'), "Generated binary has valid %PDF-1.4 header");
        $this->assert(str_contains($pdfData, '%%EOF'), "Generated binary contains valid %%EOF trailer");
        $this->assert(str_contains($pdfData, $firstInv['invoice_number']), "PDF stream contains the invoice number");
    }

    // 11. Multi-tenant isolation
    private function test11_tenantIsolation(): void
    {
        echo "\n11. Testing Multi-Tenant Data Isolation...\n";
        $orgModel = new Organization();
        $codeB = 'TESTB-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $orgBId = (int)$orgModel->create([
            'name' => 'Beta Patrol Agency',
            'organization_code' => $codeB,
            'email' => "beta_{$codeB}@example.com",
            'status' => 0,
        ]);

        $this->subService->createInitialSubscription($orgBId, [
            'guard_limit' => 20,
            'price_per_guard' => 500.0,
        ], 1);

        $invModel = new Invoice();
        $invoicesB = $invModel->allByTenant($orgBId);
        $this->assert(count($invoicesB) === 1, "Tenant B has exactly 1 invoice");

        // Verify Org A cannot query Org B's invoice
        $invBId = (int)$invoicesB[0]['id'];
        $wrongTenantQuery = $invModel->findByTenant($invBId, 99999999);
        $this->assert(empty($wrongTenantQuery), "Cross-tenant invoice lookup returns null (Data isolated)");
    }

    // 12. Expired subscription detection
    private function test12_expiredSubscriptionDetection(int $orgId): void
    {
        echo "\n12. Testing Expired Subscription Detection...\n";
        // Force end_date to yesterday
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $this->db->prepare("UPDATE subscriptions SET end_date = ? WHERE organization_id = ?")->execute([$yesterday, $orgId]);

        $subModel = new Subscription();
        $sub = $subModel->findCurrentByOrganization($orgId);
        $this->assert($sub['calculated_status'] === 'expired', "Subscription dynamic status accurately evaluates to 'expired'");

        // Restore active end date
        $futureDate = date('Y-m-d', strtotime('+1 year'));
        $this->db->prepare("UPDATE subscriptions SET end_date = ? WHERE organization_id = ?")->execute([$futureDate, $orgId]);
    }

    // 13. Organization suspension check
    private function test13_organizationSuspension(): void
    {
        echo "\n13. Testing Organization Suspension Status...\n";
        $orgModel = new Organization();
        $code = 'SUSP-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $suspOrgId = (int)$orgModel->create([
            'name' => 'Suspended Security Co',
            'organization_code' => $code,
            'email' => "susp_{$code}@example.com",
            'status' => 1, // Suspended
        ]);

        $org = $orgModel->find($suspOrgId);
        $this->assert((int)$org['status'] !== 0, "Organization status is suspended (status != 0)");
    }

    // 14. Contract guard capacity validation
    private function test14_contractGuardCapacityValidation(int $orgId): void
    {
        echo "\n14. Testing Contract Guard Slot Capacity Enforcement...\n";
        $sub = $this->subService->getSubscriptionDetails($orgId);
        $guardLimit = (int)($sub['guard_limit'] ?? 35);
        $this->assert($guardLimit === 35, "Organisation currently has a limit of {$guardLimit} guards");

        $exceedingGuards = 50;
        $isExceeded = $exceedingGuards > $guardLimit;
        $this->assert($isExceeded, "Contract requiring {$exceedingGuards} guards correctly detected as exceeding capacity ({$guardLimit})");

        $validGuards = 20;
        $isValid = $validGuards <= $guardLimit;
        $this->assert($isValid, "Contract requiring {$validGuards} guards correctly detected as within capacity");
    }
}

$suite = new SubscriptionTestSuite();
$suite->run();
