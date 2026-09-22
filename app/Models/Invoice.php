<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Invoice Model
 * Table: invoices (in secure360_v2)
 */
class Invoice extends Model
{
    protected string $table = 'invoices';

    /**
     * Find single invoice by ID with organization and subscription details
     */
    public function findWithDetails(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT i.*,
                    o.name as organization_name, o.organization_code, o.contact_person,
                    o.email as organization_email, o.phone as organization_phone, o.address as organization_address,
                    s.id as subscription_id, s.start_date as sub_start_date, s.end_date as sub_end_date,
                    s.guard_limit as sub_guard_limit, s.price_per_guard as sub_price_per_guard, s.status as sub_status
             FROM {$this->table} i
             JOIN organizations o ON i.organization_id = o.id
             JOIN subscriptions s ON i.subscription_id = s.id
             WHERE i.id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find single invoice scoped to a tenant
     */
    public function findByTenant(int $id, int $organizationId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT i.*,
                    o.name as organization_name, o.organization_code, o.contact_person,
                    o.email as organization_email, o.phone as organization_phone, o.address as organization_address,
                    s.id as subscription_id, s.start_date as sub_start_date, s.end_date as sub_end_date,
                    s.guard_limit as sub_guard_limit, s.price_per_guard as sub_price_per_guard
             FROM {$this->table} i
             JOIN organizations o ON i.organization_id = o.id
             JOIN subscriptions s ON i.subscription_id = s.id
             WHERE i.id = :id AND i.organization_id = :org_id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id, 'org_id' => $organizationId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Find invoice by unique invoice number
     */
    public function findByInvoiceNumber(string $invoiceNumber): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT i.*, o.name as organization_name, o.organization_code
             FROM {$this->table} i
             JOIN organizations o ON i.organization_id = o.id
             WHERE i.invoice_number = :num
             LIMIT 1"
        );
        $stmt->execute(['num' => $invoiceNumber]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * List all invoices for a tenant organisation (implements Model::allByTenant)
     */
    public function allByTenant(int $organisationId, string $orderBy = 'invoice_date DESC, id DESC'): array
    {
        $stmt = $this->db->prepare(
            "SELECT i.*, o.name as organization_name, o.organization_code
             FROM {$this->table} i
             JOIN organizations o ON i.organization_id = o.id
             WHERE i.organization_id = :org_id
             ORDER BY {$orderBy}"
        );
        $stmt->execute(['org_id' => $organisationId]);
        return $stmt->fetchAll();
    }

    /**
     * List all invoices for a tenant organisation with pagination
     */
    public function paginateByTenant(int $organizationId, int $limit = 10, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            "SELECT i.*, o.name as organization_name, o.organization_code
             FROM {$this->table} i
             JOIN organizations o ON i.organization_id = o.id
             WHERE i.organization_id = :org_id
             ORDER BY i.invoice_date DESC, i.id DESC
             LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue(':org_id', $organizationId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Count invoices for a tenant organisation
     */
    public function countByTenant(int $organizationId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE organization_id = :org_id"
        );
        $stmt->execute(['org_id' => $organizationId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Ensure required columns exist in invoices table
     */
    public function ensureColumns(): void
    {
        static $checked = false;
        if ($checked) return;
        try {
            $cols = $this->db->query("SHOW COLUMNS FROM {$this->table} LIKE 'invoice_type'")->fetchAll();
            if (empty($cols)) {
                $this->db->exec("ALTER TABLE `{$this->table}`
                    ADD COLUMN `invoice_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Initial Subscription' AFTER `subscription_id`,
                    ADD COLUMN `previous_guard_limit` int unsigned DEFAULT NULL AFTER `guard_quantity`,
                    ADD COLUMN `additional_guards` int unsigned DEFAULT NULL AFTER `previous_guard_limit`");
            }
        } catch (\Throwable) {
            // Ignore error if schema already up to date
        }
        $checked = true;
    }

    /**
     * List all invoices globally for Superadmin with pagination
     */
    public function allGlobal(int $limit = 10, int $offset = 0): array
    {
        $this->ensureColumns();
        $stmt = $this->db->prepare(
            "SELECT i.*, o.name as organization_name, o.organization_code
             FROM {$this->table} i
             JOIN organizations o ON i.organization_id = o.id
             ORDER BY i.invoice_date DESC, i.id DESC
             LIMIT :lim OFFSET :off"
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * List all invoices globally for Superadmin with filter and search
     */
    public function allGlobalPaginated(int $limit = 10, int $offset = 0, string $statusFilter = 'all', string $search = ''): array
    {
        $this->ensureColumns();
        $sql = "SELECT i.*, o.name as organization_name, o.organization_code
                FROM {$this->table} i
                JOIN organizations o ON i.organization_id = o.id
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (i.invoice_number LIKE :s1 OR o.name LIKE :s2 OR o.organization_code LIKE :s3)";
            $params['s1'] = "%{$search}%";
            $params['s2'] = "%{$search}%";
            $params['s3'] = "%{$search}%";
        }

        if ($statusFilter === 'paid') {
            $sql .= " AND i.status = 'paid'";
        } elseif ($statusFilter === 'pending') {
            $sql .= " AND i.status = 'pending'";
        } elseif ($statusFilter === 'cancelled') {
            $sql .= " AND i.status = 'cancelled'";
        }

        $sql .= " ORDER BY i.invoice_date DESC, i.id DESC LIMIT :lim OFFSET :off";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(":{$k}", $v);
        }
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get all invoices matching search and filter WITHOUT pagination (for exports)
     */
    public function allGlobalFiltered(string $statusFilter = 'all', string $search = ''): array
    {
        $this->ensureColumns();
        $sql = "SELECT i.*, o.name as organization_name, o.organization_code
                FROM {$this->table} i
                JOIN organizations o ON i.organization_id = o.id
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (i.invoice_number LIKE :s1 OR o.name LIKE :s2 OR o.organization_code LIKE :s3)";
            $params['s1'] = "%{$search}%";
            $params['s2'] = "%{$search}%";
            $params['s3'] = "%{$search}%";
        }

        if ($statusFilter === 'paid') {
            $sql .= " AND i.status = 'paid'";
        } elseif ($statusFilter === 'pending') {
            $sql .= " AND i.status = 'pending'";
        } elseif ($statusFilter === 'cancelled') {
            $sql .= " AND i.status = 'cancelled'";
        }

        $sql .= " ORDER BY i.invoice_date DESC, i.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Count invoices globally for Superadmin with filter and search
     */
    public function countGlobalFiltered(string $statusFilter = 'all', string $search = ''): int
    {
        $this->ensureColumns();
        $sql = "SELECT COUNT(*)
                FROM {$this->table} i
                JOIN organizations o ON i.organization_id = o.id
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (i.invoice_number LIKE :s1 OR o.name LIKE :s2 OR o.organization_code LIKE :s3)";
            $params['s1'] = "%{$search}%";
            $params['s2'] = "%{$search}%";
            $params['s3'] = "%{$search}%";
        }

        if ($statusFilter === 'paid') {
            $sql .= " AND i.status = 'paid'";
        } elseif ($statusFilter === 'pending') {
            $sql .= " AND i.status = 'pending'";
        } elseif ($statusFilter === 'cancelled') {
            $sql .= " AND i.status = 'cancelled'";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get counts for invoice tabs
     */
    public function getStatusCounts(): array
    {
        $this->ensureColumns();
        $all = (int)$this->db->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
        $paid = (int)$this->db->query("SELECT COUNT(*) FROM {$this->table} WHERE status = 'paid'")->fetchColumn();
        $pending = (int)$this->db->query("SELECT COUNT(*) FROM {$this->table} WHERE status = 'pending'")->fetchColumn();
        $cancelled = (int)$this->db->query("SELECT COUNT(*) FROM {$this->table} WHERE status = 'cancelled'")->fetchColumn();
        return [
            'all' => $all,
            'paid' => $paid,
            'pending' => $pending,
            'cancelled' => $cancelled,
        ];
    }

    /**
     * Count invoices globally for Superadmin
     */
    public function countGlobal(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM {$this->table}");
        return (int)$stmt->fetchColumn();
    }

    /**
     * Generate sequential, unique invoice number (e.g. INV-2026-000001)
     */
    public function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $prefix = "INV-{$year}-";

        $stmt = $this->db->prepare(
            "SELECT invoice_number FROM {$this->table}
             WHERE invoice_number LIKE :prefix
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute(['prefix' => "{$prefix}%"]);
        $last = $stmt->fetchColumn();

        if ($last && preg_match('/INV-\d{4}-(\d+)/', $last, $matches)) {
            $next = (int)$matches[1] + 1;
            return $prefix . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
        }

        return $prefix . '000001';
    }
}
