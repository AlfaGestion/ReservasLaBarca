<?php

namespace App\Models;

use CodeIgniter\Model;

class ServicePriceWindowsModel extends Model
{
    protected $table = 'service_price_windows';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['service_id', 'weekdays', 'start_time', 'end_time', 'price', 'active', 'created_at', 'updated_at'];
    protected $useTimestamps = false;

    public function forService(int $serviceId): array
    {
        if ($serviceId <= 0 || ! $this->db->tableExists($this->table)) {
            return [];
        }

        return $this->where('service_id', $serviceId)->where('active', 1)->orderBy('start_time', 'ASC')->findAll();
    }
}
