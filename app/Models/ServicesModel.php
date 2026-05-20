<?php

namespace App\Models;

use CodeIgniter\Model;

class ServicesModel extends Model
{
    public const DEFAULT_SERVICES = [
        'football' => [
            'name' => 'Cancha / Fútbol',
            'code' => 'football',
            'opening_time' => '07:00:00',
            'closing_time' => '23:00:00',
            'duration_minutes' => 60,
            'slot_interval_minutes' => 60,
            'minimum_duration_minutes' => 60,
            'booking_interval_minutes' => 60,
            'active' => 1,
            'online_available' => 1,
            'allows_quincho_addon' => 1,
            'display_order' => 10,
            'color' => '#198754',
            'icon' => 'fa-futbol',
        ],
        'padel' => [
            'name' => 'Pádel',
            'code' => 'padel',
            'opening_time' => '07:00:00',
            'closing_time' => '23:00:00',
            'duration_minutes' => 90,
            'slot_interval_minutes' => 90,
            'minimum_duration_minutes' => 90,
            'booking_interval_minutes' => 90,
            'active' => 1,
            'online_available' => 1,
            'allows_quincho_addon' => 1,
            'display_order' => 20,
            'color' => '#0d6efd',
            'icon' => 'fa-table-tennis-paddle-ball',
        ],
        'quincho' => [
            'name' => 'Quincho',
            'code' => 'quincho',
            'opening_time' => '07:00:00',
            'closing_time' => '23:00:00',
            'duration_minutes' => 60,
            'slot_interval_minutes' => 60,
            'minimum_duration_minutes' => 60,
            'booking_interval_minutes' => 60,
            'active' => 1,
            'online_available' => 1,
            'allows_quincho_addon' => 0,
            'display_order' => 30,
            'color' => '#6c757d',
            'icon' => 'fa-champagne-glasses',
        ],
        'eventos' => [
            'name' => 'Eventos / Confitería',
            'code' => 'eventos',
            'opening_time' => '07:00:00',
            'closing_time' => '23:00:00',
            'duration_minutes' => 60,
            'slot_interval_minutes' => 60,
            'minimum_duration_minutes' => 60,
            'booking_interval_minutes' => 60,
            'active' => 1,
            'online_available' => 1,
            'allows_quincho_addon' => 1,
            'display_order' => 40,
            'color' => '#dc3545',
            'icon' => 'fa-calendar-check',
        ],
    ];

    protected $DBGroup = 'default';
    protected $table = 'services';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'name',
        'code',
        'opening_time',
        'closing_time',
        'duration_minutes',
        'slot_interval_minutes',
        'minimum_duration_minutes',
        'booking_interval_minutes',
        'active',
        'online_available',
        'allows_quincho_addon',
        'display_order',
        'color',
        'icon',
        'offer_active',
        'offer_text',
        'discount_type',
        'discount_value',
        'offer_start_date',
        'offer_end_date',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;

    public function ensureDefaultServices(): void
    {
        $this->ensureSchema();

        foreach (self::DEFAULT_SERVICES as $code => $default) {
            $existing = $this->where('code', $code)->first();
            if ($existing) {
                $payload = [
                    'slot_interval_minutes' => (int)($existing['slot_interval_minutes'] ?? 0) > 0 ? $existing['slot_interval_minutes'] : ($existing['duration_minutes'] ?? $default['duration_minutes']),
                    'minimum_duration_minutes' => (int)($existing['minimum_duration_minutes'] ?? 0) > 0 ? $existing['minimum_duration_minutes'] : ($existing['duration_minutes'] ?? $default['duration_minutes']),
                    'booking_interval_minutes' => (int)($existing['booking_interval_minutes'] ?? 0) > 0 ? $existing['booking_interval_minutes'] : ($existing['duration_minutes'] ?? $default['duration_minutes']),
                    'display_order' => (int)($existing['display_order'] ?? 0) > 0 ? $existing['display_order'] : $default['display_order'],
                ];
                $this->update($existing['id'], $payload);
                continue;
            }

            $default['created_at'] = date('Y-m-d H:i:s');
            $default['updated_at'] = date('Y-m-d H:i:s');
            $this->insert($default);
        }
    }

    private function ensureSchema(): void
    {
        if ($this->db->tableExists($this->table)) {
            $columns = [
                'opening_time' => "ALTER TABLE services ADD COLUMN opening_time TIME NOT NULL DEFAULT '07:00:00' AFTER code",
                'closing_time' => "ALTER TABLE services ADD COLUMN closing_time TIME NOT NULL DEFAULT '23:00:00' AFTER opening_time",
                'duration_minutes' => "ALTER TABLE services ADD COLUMN duration_minutes INT NOT NULL DEFAULT 60 AFTER closing_time",
                'slot_interval_minutes' => "ALTER TABLE services ADD COLUMN slot_interval_minutes INT NOT NULL DEFAULT 60 AFTER duration_minutes",
                'minimum_duration_minutes' => "ALTER TABLE services ADD COLUMN minimum_duration_minutes INT NOT NULL DEFAULT 60 AFTER slot_interval_minutes",
                'booking_interval_minutes' => "ALTER TABLE services ADD COLUMN booking_interval_minutes INT NOT NULL DEFAULT 60 AFTER minimum_duration_minutes",
                'active' => "ALTER TABLE services ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER booking_interval_minutes",
                'online_available' => "ALTER TABLE services ADD COLUMN online_available TINYINT(1) NOT NULL DEFAULT 1 AFTER active",
                'allows_quincho_addon' => "ALTER TABLE services ADD COLUMN allows_quincho_addon TINYINT(1) NOT NULL DEFAULT 1 AFTER online_available",
                'display_order' => "ALTER TABLE services ADD COLUMN display_order INT NOT NULL DEFAULT 0 AFTER allows_quincho_addon",
                'color' => "ALTER TABLE services ADD COLUMN color VARCHAR(20) NULL AFTER display_order",
                'icon' => "ALTER TABLE services ADD COLUMN icon VARCHAR(80) NULL AFTER color",
                'offer_active' => "ALTER TABLE services ADD COLUMN offer_active TINYINT(1) NOT NULL DEFAULT 0 AFTER icon",
                'offer_text' => "ALTER TABLE services ADD COLUMN offer_text VARCHAR(255) NULL AFTER offer_active",
                'discount_type' => "ALTER TABLE services ADD COLUMN discount_type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage' AFTER offer_text",
                'discount_value' => "ALTER TABLE services ADD COLUMN discount_value DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER discount_type",
                'offer_start_date' => "ALTER TABLE services ADD COLUMN offer_start_date DATE NULL AFTER discount_value",
                'offer_end_date' => "ALTER TABLE services ADD COLUMN offer_end_date DATE NULL AFTER offer_start_date",
                'created_at' => "ALTER TABLE services ADD COLUMN created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER offer_end_date",
                'updated_at' => "ALTER TABLE services ADD COLUMN updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
            ];
            foreach ($columns as $column => $sql) {
                if (! $this->db->fieldExists($column, $this->table)) {
                    $this->db->query($sql);
                }
            }
            return;
        }

        $this->db->query("
            CREATE TABLE IF NOT EXISTS services (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(120) NOT NULL,
                code VARCHAR(40) NOT NULL,
                opening_time TIME NOT NULL DEFAULT '07:00:00',
                closing_time TIME NOT NULL DEFAULT '23:00:00',
                duration_minutes INT NOT NULL DEFAULT 60,
                slot_interval_minutes INT NOT NULL DEFAULT 60,
                minimum_duration_minutes INT NOT NULL DEFAULT 60,
                booking_interval_minutes INT NOT NULL DEFAULT 60,
                active TINYINT(1) NOT NULL DEFAULT 1,
                online_available TINYINT(1) NOT NULL DEFAULT 1,
                allows_quincho_addon TINYINT(1) NOT NULL DEFAULT 1,
                display_order INT NOT NULL DEFAULT 0,
                color VARCHAR(20) NULL,
                icon VARCHAR(80) NULL,
                offer_active TINYINT(1) NOT NULL DEFAULT 0,
                offer_text VARCHAR(255) NULL,
                discount_type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
                discount_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                offer_start_date DATE NULL,
                offer_end_date DATE NULL,
                created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_services_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function getServices(): array
    {
        $this->ensureDefaultServices();

        return $this->orderBy('display_order', 'ASC')->orderBy('id', 'ASC')->findAll();
    }

    public function getActiveServices(bool $onlineOnly = false): array
    {
        $this->ensureDefaultServices();

        $builder = $this->where('active', 1);
        if ($onlineOnly) {
            $builder->where('online_available', 1);
        }

        return $builder->orderBy('display_order', 'ASC')->orderBy('id', 'ASC')->findAll();
    }

    public function getByCode(string $code): ?array
    {
        if (! $this->db->tableExists($this->table)) {
            return null;
        }

        return $this->where('code', $code)->first();
    }

    public function getByField(array $field): ?array
    {
        if (! empty($field['service_id']) && $this->db->tableExists($this->table)) {
            $service = $this->find($field['service_id']);
            if ($service) {
                return $service;
            }
        }

        return $this->getByCode((string)($field['service_type'] ?? 'football'));
    }
}
