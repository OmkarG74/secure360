<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Subscription Model
 * Table: subscriptions (in secure360_v2)
 */
class Subscription extends Model
{
    protected string $table = 'subscriptions';

    /**
     * Find active / latest subscription for an organization
     */
    public function findCurrentByOrganization(int $organizationId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, o.name as organization_name, o.organization_code, o.status as org_status
             FROM {$this->table} s
             JOIN organizations o ON s.organization_id = o.id
             WHERE s.organization_id = :org_id
             ORDER BY s.id DESC
             LIMIT 1"
        );
        $stmt->execute(['org_id' => $organizationId]);
        $row = $stmt->fetch();

        if ($row) {
            $row['calculated_status'] = $this->calculateDynamicStatus($row);
            $row['days_remaining'] = $this->calculateDaysRemaining((string)$row['end_date']);
        }

        return $row ?: null;
    }

    /**
     * Get all subscriptions for an organization (for history view)
     */
    public function allByOrganization(int $organizationId): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, u.full_name as creator_name
             FROM {$this->table} s
             LEFT JOIN users u ON s.created_by = u.id
             WHERE s.organization_id = :org_id
             ORDER BY s.id DESC"
        );
        $stmt->execute(['org_id' => $organizationId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['calculated_status'] = $this->calculateDynamicStatus($row);
            $row['days_remaining'] = $this->calculateDaysRemaining((string)$row['end_date']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Get subscription history log records
     */
    public function getHistory(int $subscriptionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT sh.*, u.full_name as performed_by_name
             FROM subscription_history sh
             LEFT JOIN users u ON sh.performed_by = u.id
             WHERE sh.subscription_id = :sub_id
             ORDER BY sh.id DESC"
        );
        $stmt->execute(['sub_id' => $subscriptionId]);
        return $stmt->fetchAll();
    }

    /**
     * Get all history log records for an organization
     */
    public function getHistoryByOrganization(int $organizationId): array
    {
        $stmt = $this->db->prepare(
            "SELECT sh.*, u.full_name as performed_by_name
             FROM subscription_history sh
             LEFT JOIN users u ON sh.performed_by = u.id
             WHERE sh.organization_id = :org_id
             ORDER BY sh.id DESC"
        );
        $stmt->execute(['org_id' => $organizationId]);
        return $stmt->fetchAll();
    }

    /**
     * Record a subscription history entry
     */
    public function recordHistory(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO subscription_history
             (subscription_id, organization_id, change_type, previous_guard_limit, new_guard_limit,
              previous_price_per_guard, new_price_per_guard, previous_total_amount, new_total_amount,
              amount_difference, effective_date, performed_by, notes, created_at)
             VALUES
             (:subscription_id, :organization_id, :change_type, :previous_guard_limit, :new_guard_limit,
              :previous_price_per_guard, :new_price_per_guard, :previous_total_amount, :new_total_amount,
              :amount_difference, :effective_date, :performed_by, :notes, NOW())"
        );

        $stmt->execute([
            'subscription_id' => $data['subscription_id'],
            'organization_id' => $data['organization_id'],
            'change_type' => $data['change_type'],
            'previous_guard_limit' => $data['previous_guard_limit'] ?? null,
            'new_guard_limit' => $data['new_guard_limit'],
            'previous_price_per_guard' => $data['previous_price_per_guard'] ?? null,
            'new_price_per_guard' => $data['new_price_per_guard'],
            'previous_total_amount' => $data['previous_total_amount'] ?? null,
            'new_total_amount' => $data['new_total_amount'],
            'amount_difference' => $data['amount_difference'] ?? 0.00,
            'effective_date' => $data['effective_date'] ?? date('Y-m-d'),
            'performed_by' => $data['performed_by'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Calculate dynamic subscription status based on server dates and database record
     */
    public function calculateDynamicStatus(array $sub): string
    {
        $rawStatus = (int)($sub['status'] ?? SUBSCRIPTION_ACTIVE);

        if ($rawStatus === SUBSCRIPTION_CANCELLED) {
            return 'cancelled';
        }

        if ($rawStatus === SUBSCRIPTION_SUSPENDED) {
            return 'suspended';
        }

        $endDateStr = (string)($sub['end_date'] ?? '');
        if ($endDateStr === '') {
            return 'active';
        }

        $today = new \DateTime('today');
        $endDate = new \DateTime($endDateStr);

        if ($today > $endDate) {
            return 'expired';
        }

        $interval = $today->diff($endDate);
        $daysRemaining = (int)$interval->format('%r%a');

        $settingModel = new SystemSetting();
        $warningDays = $settingModel->getExpiringSoonDays();

        if ($daysRemaining <= $warningDays) {
            return 'expiring_soon';
        }

        return 'active';
    }

    /**
     * Days remaining until end_date
     */
    public function calculateDaysRemaining(string $endDateStr): int
    {
        if ($endDateStr === '') return 0;
        $today = new \DateTime('today');
        $endDate = new \DateTime($endDateStr);
        $diff = $today->diff($endDate);
        return (int)$diff->format('%r%a');
    }

    /**
     * Find single subscription with complete organization and creator details
     */
    public function findWithDetails(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*,
                    o.name as organization_name, o.organization_code, o.status as org_status,
                    o.email as organization_email, o.phone as organization_phone, o.address as organization_address,
                    o.contact_person,
                    u.full_name as creator_name
             FROM {$this->table} s
             JOIN organizations o ON s.organization_id = o.id
             LEFT JOIN users u ON s.created_by = u.id
             WHERE s.id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row) {
            $row['calculated_status'] = $this->calculateDynamicStatus($row);
            $row['days_remaining'] = $this->calculateDaysRemaining((string)$row['end_date']);
        }

        return $row ?: null;
    }

    /**
     * Get all subscriptions globally with search, filter, and pagination for Superadmin
     */
    public function allGlobalPaginated(int $limit = 10, int $offset = 0, string $statusFilter = 'all', string $search = ''): array
    {
        $sql = "SELECT s.*, o.name as organization_name, o.organization_code, o.status as org_status
                FROM {$this->table} s
                JOIN organizations o ON s.organization_id = o.id
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (o.name LIKE :search OR o.organization_code LIKE :search2 OR s.id = :searchId)";
            $params['search'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
            $params['searchId'] = is_numeric($search) ? (int)$search : 0;
        }

        $today = date('Y-m-d');
        $settingModel = new SystemSetting();
        $warningDays = $settingModel->getExpiringSoonDays();
        $warningDate = date('Y-m-d', strtotime("+{$warningDays} days"));

        if ($statusFilter === 'active') {
            $sql .= " AND s.status = 0 AND s.end_date >= :today_active AND s.end_date > :warn_active";
            $params['today_active'] = $today;
            $params['warn_active'] = $warningDate;
        } elseif ($statusFilter === 'expiring') {
            $sql .= " AND s.status = 0 AND s.end_date >= :today_exp AND s.end_date <= :warn_exp";
            $params['today_exp'] = $today;
            $params['warn_exp'] = $warningDate;
        } elseif ($statusFilter === 'expired') {
            $sql .= " AND (s.status = 2 OR (s.status = 0 AND s.end_date < :today_expired))";
            $params['today_expired'] = $today;
        } elseif ($statusFilter === 'suspended') {
            $sql .= " AND s.status = 3";
        }

        $sql .= " ORDER BY s.id DESC LIMIT :lim OFFSET :off";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(":{$k}", $v);
        }
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $guardModel = new Guard();

        foreach ($rows as &$row) {
            $row['calculated_status'] = $this->calculateDynamicStatus($row);
            $row['days_remaining'] = $this->calculateDaysRemaining((string)$row['end_date']);
            $row['active_guards'] = $guardModel->countActiveGuards((int)$row['organization_id']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Get all subscriptions matching search and filter WITHOUT pagination (for exports)
     */
    public function allGlobalFiltered(string $statusFilter = 'all', string $search = ''): array
    {
        $sql = "SELECT s.*, o.name as organization_name, o.organization_code, o.status as org_status
                FROM {$this->table} s
                JOIN organizations o ON s.organization_id = o.id
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (o.name LIKE :search OR o.organization_code LIKE :search2 OR s.id = :searchId)";
            $params['search'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
            $params['searchId'] = is_numeric($search) ? (int)$search : 0;
        }

        $today = date('Y-m-d');
        $settingModel = new SystemSetting();
        $warningDays = $settingModel->getExpiringSoonDays();
        $warningDate = date('Y-m-d', strtotime("+{$warningDays} days"));

        if ($statusFilter === 'active') {
            $sql .= " AND s.status = 0 AND s.end_date >= :today_active AND s.end_date > :warn_active";
            $params['today_active'] = $today;
            $params['warn_active'] = $warningDate;
        } elseif ($statusFilter === 'expiring') {
            $sql .= " AND s.status = 0 AND s.end_date >= :today_exp AND s.end_date <= :warn_exp";
            $params['today_exp'] = $today;
            $params['warn_exp'] = $warningDate;
        } elseif ($statusFilter === 'expired') {
            $sql .= " AND (s.status = 2 OR (s.status = 0 AND s.end_date < :today_expired))";
            $params['today_expired'] = $today;
        } elseif ($statusFilter === 'suspended') {
            $sql .= " AND s.status = 3";
        }

        $sql .= " ORDER BY s.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll();
        $guardModel = new Guard();

        foreach ($rows as &$row) {
            $row['calculated_status'] = $this->calculateDynamicStatus($row);
            $row['days_remaining'] = $this->calculateDaysRemaining((string)$row['end_date']);
            $row['active_guards'] = $guardModel->countActiveGuards((int)$row['organization_id']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Count global subscriptions with search and filter
     */
    public function countGlobalFiltered(string $statusFilter = 'all', string $search = ''): int
    {
        $sql = "SELECT COUNT(*)
                FROM {$this->table} s
                JOIN organizations o ON s.organization_id = o.id
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (o.name LIKE :search OR o.organization_code LIKE :search2 OR s.id = :searchId)";
            $params['search'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
            $params['searchId'] = is_numeric($search) ? (int)$search : 0;
        }

        $today = date('Y-m-d');
        $settingModel = new SystemSetting();
        $warningDays = $settingModel->getExpiringSoonDays();
        $warningDate = date('Y-m-d', strtotime("+{$warningDays} days"));

        if ($statusFilter === 'active') {
            $sql .= " AND s.status = 0 AND s.end_date >= :today_active AND s.end_date > :warn_active";
            $params['today_active'] = $today;
            $params['warn_active'] = $warningDate;
        } elseif ($statusFilter === 'expiring') {
            $sql .= " AND s.status = 0 AND s.end_date >= :today_exp AND s.end_date <= :warn_exp";
            $params['today_exp'] = $today;
            $params['warn_exp'] = $warningDate;
        } elseif ($statusFilter === 'expired') {
            $sql .= " AND (s.status = 2 OR (s.status = 0 AND s.end_date < :today_expired))";
            $params['today_expired'] = $today;
        } elseif ($statusFilter === 'suspended') {
            $sql .= " AND s.status = 3";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get counts for all status filter tabs
     */
    public function getStatusCounts(): array
    {
        $today = date('Y-m-d');
        $settingModel = new SystemSetting();
        $warningDays = $settingModel->getExpiringSoonDays();
        $warningDate = date('Y-m-d', strtotime("+{$warningDays} days"));

        $all = (int)$this->db->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();

        $activeStmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE status = 0 AND end_date >= :today AND end_date > :warn");
        $activeStmt->execute(['today' => $today, 'warn' => $warningDate]);
        $active = (int)$activeStmt->fetchColumn();

        $expiringStmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE status = 0 AND end_date >= :today AND end_date <= :warn");
        $expiringStmt->execute(['today' => $today, 'warn' => $warningDate]);
        $expiring = (int)$expiringStmt->fetchColumn();

        $expiredStmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE status = 2 OR (status = 0 AND end_date < :today)");
        $expiredStmt->execute(['today' => $today]);
        $expired = (int)$expiredStmt->fetchColumn();

        $suspended = (int)$this->db->query("SELECT COUNT(*) FROM {$this->table} WHERE status = 3")->fetchColumn();

        return [
            'all' => $all,
            'active' => $active,
            'expiring' => $expiring,
            'expired' => $expired,
            'suspended' => $suspended,
        ];
    }
}
