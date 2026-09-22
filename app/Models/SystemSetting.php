<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * SystemSetting Model
 * Key-value platform configurations stored in `system_settings` table
 */
class SystemSetting extends Model
{
    protected string $table = 'system_settings';

    /**
     * Get a setting value by key with optional default fallback
     */
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT setting_value FROM {$this->table} WHERE setting_key = :k LIMIT 1"
            );
            $stmt->execute(['k' => $key]);
            $val = $stmt->fetchColumn();

            return $val !== false ? $val : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * Set / Update a setting value
     */
    public function set(string $key, mixed $value, ?string $description = null): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (setting_key, setting_value, description, updated_at)
             VALUES (:k, :v, :d, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
        );

        return $stmt->execute([
            'k' => $key,
            'v' => (string)$value,
            'd' => $description,
        ]);
    }

    /**
     * Get default price per guard (fallback 500.00)
     */
    public function getDefaultPricePerGuard(): float
    {
        $val = $this->get('subscription_price_per_guard', '500.00');
        $floatVal = (float)$val;
        return $floatVal > 0 ? $floatVal : 500.00;
    }

    /**
     * Get platform currency code (fallback INR)
     */
    public function getCurrency(): string
    {
        return (string)$this->get('subscription_currency', 'INR');
    }

    /**
     * Get days threshold for "Expiring Soon" status (fallback 15)
     */
    public function getExpiringSoonDays(): int
    {
        $val = (int)$this->get('subscription_expiring_soon_days', 15);
        return $val > 0 ? $val : 15;
    }
}
