<?php

namespace App\Models;

use CodeIgniter\Model;

class ServicesModel extends Model
{
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

    public function getServices(): array
    {
        if (! $this->db->tableExists($this->table)) {
            return [];
        }

        return $this->orderBy('display_order', 'ASC')->orderBy('id', 'ASC')->findAll();
    }

    public function getActiveServices(bool $onlineOnly = false): array
    {
        if (! $this->db->tableExists($this->table)) {
            return [];
        }

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
