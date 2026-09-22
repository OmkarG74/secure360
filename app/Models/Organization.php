<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Organization Model (Customer Tenant Entity)
 * Table: organizations (in secure360_v2)
 */
class Organization extends Model
{
    protected string $table = 'organizations';

    /**
     * Find active organization by unique organization code
     */
    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE organization_code = :code AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute(['code' => $code]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Retrieve all non-deleted organizations for Superadmin
     */
    public function allActive(): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE deleted_at IS NULL ORDER BY name ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Common server-side filtering logic for Organisations Table, Excel Export, and PDF Export.
     *
     * @param array $filters ['status' => 'all'|'0'|'1', 'search'|'q' => string]
     * @param int|null $limit Null means no pagination limit (used for exports)
     * @param int $offset
     * @return array
     */
    public function getFilteredOrganisations(array $filters = [], ?int $limit = null, int $offset = 0): array
    {
        $params = [];
        $where = ["o.deleted_at IS NULL"];

        // 1. Status Filter
        $status = $filters['status'] ?? 'all';
        if ($status !== 'all' && $status !== '' && $status !== null) {
            $where[] = "o.status = :status";
            $params['status'] = (int)$status;
        }

        // 2. Search Query (matches org name, code, contact person, email, phone, or admin details)
        $search = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = "(
                o.name LIKE :search_name
                OR o.organization_code LIKE :search_code
                OR o.contact_person LIKE :search_contact
                OR o.email LIKE :search_email
                OR o.phone LIKE :search_phone
                OR EXISTS (
                    SELECT 1 FROM users u
                    WHERE u.organization_id = o.id
                      AND u.role_id = 2
                      AND u.deleted_at IS NULL
                      AND (u.full_name LIKE :search_admin_name OR u.email LIKE :search_admin_email OR u.phone LIKE :search_admin_phone)
                )
            )";
            $searchWildcard = '%' . $search . '%';
            $params['search_name'] = $searchWildcard;
            $params['search_code'] = $searchWildcard;
            $params['search_contact'] = $searchWildcard;
            $params['search_email'] = $searchWildcard;
            $params['search_phone'] = $searchWildcard;
            $params['search_admin_name'] = $searchWildcard;
            $params['search_admin_email'] = $searchWildcard;
            $params['search_admin_phone'] = $searchWildcard;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT
                    o.*,
                    (SELECT COUNT(*) FROM users u2 WHERE u2.organization_id = o.id AND u2.role_id = 2 AND u2.deleted_at IS NULL) as admin_count,
                    (SELECT COUNT(*) FROM guards g JOIN users ug ON g.user_id = ug.id WHERE ug.organization_id = o.id AND g.status = 0 AND g.deleted_at IS NULL) as active_guards,
                    (SELECT s.guard_limit FROM subscriptions s WHERE s.organization_id = o.id ORDER BY s.id DESC LIMIT 1) as guard_limit,
                    (SELECT s.status FROM subscriptions s WHERE s.organization_id = o.id ORDER BY s.id DESC LIMIT 1) as subscription_status,
                    (SELECT s.start_date FROM subscriptions s WHERE s.organization_id = o.id ORDER BY s.id DESC LIMIT 1) as subscription_start,
                    (SELECT s.end_date FROM subscriptions s WHERE s.organization_id = o.id ORDER BY s.id DESC LIMIT 1) as subscription_end
                FROM {$this->table} o
                WHERE {$whereClause}
                ORDER BY o.name ASC";

        if ($limit !== null && $limit > 0) {
            $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $organizations = $stmt->fetchAll();

        // Attach administrators to each organization
        $userModel = new User();
        foreach ($organizations as &$org) {
            $orgId = (int)$org['id'];
            $org['admins'] = $userModel->findAdminsByOrganization($orgId);
        }
        unset($org);

        return $organizations;
    }

    /**
     * Count total organisations matching the given filters (for pagination and export statistics)
     */
    public function countFilteredOrganisations(array $filters = []): int
    {
        $params = [];
        $where = ["o.deleted_at IS NULL"];

        $status = $filters['status'] ?? 'all';
        if ($status !== 'all' && $status !== '' && $status !== null) {
            $where[] = "o.status = :status";
            $params['status'] = (int)$status;
        }

        $search = trim((string)($filters['search'] ?? $filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = "(
                o.name LIKE :search_name
                OR o.organization_code LIKE :search_code
                OR o.contact_person LIKE :search_contact
                OR o.email LIKE :search_email
                OR o.phone LIKE :search_phone
                OR EXISTS (
                    SELECT 1 FROM users u
                    WHERE u.organization_id = o.id
                      AND u.role_id = 2
                      AND u.deleted_at IS NULL
                      AND (u.full_name LIKE :search_admin_name OR u.email LIKE :search_admin_email OR u.phone LIKE :search_admin_phone)
                )
            )";
            $searchWildcard = '%' . $search . '%';
            $params['search_name'] = $searchWildcard;
            $params['search_code'] = $searchWildcard;
            $params['search_contact'] = $searchWildcard;
            $params['search_email'] = $searchWildcard;
            $params['search_phone'] = $searchWildcard;
            $params['search_admin_name'] = $searchWildcard;
            $params['search_admin_email'] = $searchWildcard;
            $params['search_admin_phone'] = $searchWildcard;
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) as total FROM {$this->table} o WHERE {$whereClause}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch();
        return (int)($res['total'] ?? 0);
    }

    /**
     * Get counts for All, Active, and Suspended organisations (respecting search query)
     */
    public function getStatusCounts(string $search = ''): array
    {
        $total = $this->countFilteredOrganisations(['status' => 'all', 'search' => $search]);
        $active = $this->countFilteredOrganisations(['status' => '0', 'search' => $search]);
        $suspended = $this->countFilteredOrganisations(['status' => '1', 'search' => $search]);

        return [
            'all' => $total,
            'active' => $active,
            'suspended' => $suspended,
        ];
    }
}
