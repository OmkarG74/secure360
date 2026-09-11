<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Base Application Model
 * Provides database access and multi-tenant organisation scoping
 */
abstract class Model
{
    protected PDO $db;
    protected string $table = '';
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Get the table name
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Find a record by primary key
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Scope query to the current tenant organisation (Multi-Tenant isolation)
     */
    public function findByTenant(int $id, int $organisationId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id AND organisation_id = :org_id LIMIT 1"
        );
        $stmt->execute(['id' => $id, 'org_id' => $organisationId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Retrieve all records scoped to an organisation
     */
    public function allByTenant(int $organisationId, string $orderBy = 'id DESC'): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE organization_id = :org_id AND deleted_at IS NULL ORDER BY {$orderBy}");
        $stmt->execute(['org_id' => $organisationId]);
        return $stmt->fetchAll();
    }

    /**
     * Insert a new record into table and return last insert ID
     */
    public function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":{$col}", $columns);

        $sql = sprintf(
            "INSERT INTO `%s` (`%s`) VALUES (%s)",
            $this->table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Update an existing record by ID
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        foreach (array_keys($data) as $col) {
            $fields[] = "`{$col}` = :{$col}";
        }
        $data['id'] = $id;

        $sql = sprintf(
            "UPDATE `%s` SET %s WHERE `%s` = :id",
            $this->table,
            implode(', ', $fields),
            $this->primaryKey
        );

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    /**
     * Soft delete a record by setting deleted_at = NOW()
     */
    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE `{$this->table}` SET `deleted_at` = NOW() WHERE `{$this->primaryKey}` = :id"
        );
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Hard delete a record by ID
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function beginTransaction(): bool
    {
        return $this->db->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->db->commit();
    }

    public function rollBack(): bool
    {
        return $this->db->rollBack();
    }
}
