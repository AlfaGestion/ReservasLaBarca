<?php

namespace App\Models;

use CodeIgniter\Model;

class ServicePricesModel extends Model
{
    protected $DBGroup = 'default';
    protected $table = 'service_prices';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $allowedFields = [
        'service_id',
        'base_price',
        'charge_type',
        'deposit_price',
        'active',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;

    public function getActiveForService(int $serviceId): ?array
    {
        if (! $this->db->tableExists($this->table)) {
            return null;
        }

        return $this->where('service_id', $serviceId)->where('active', 1)->orderBy('id', 'DESC')->first();
    }
}
