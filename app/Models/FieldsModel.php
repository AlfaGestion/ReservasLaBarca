<?php

namespace App\Models;

use CodeIgniter\Model;

class FieldsModel extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'fields';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['name', 'floor_type', 'sizes', 'ilumination', 'field_type', 'service_id', 'service_type', 'block_minutes', 'price_unit_label', 'roofed', 'value', 'ilumination_value', 'elements_rent', 'disabled'];

    // Dates
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    public function getFields(){
        $fields = $this->where('disabled', 0)
            ->orderBy('service_type', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        return array_values(array_filter($this->enrichFields($fields), static function ($field) {
            return (int)($field['service_active'] ?? 1) === 1 && (int)($field['online_available'] ?? 1) === 1;
        }));
    }

    public function getByServiceType(string $serviceType)
    {
        $fields = $this->where('disabled', 0)
            ->where('service_type', $serviceType)
            ->orderBy('id', 'ASC')
            ->findAll();

        return $this->enrichFields($fields);
    }

    public function getQuincho()
    {
        $field = $this->where('disabled', 0)
            ->where('service_type', 'quincho')
            ->orderBy('id', 'ASC')
            ->first();

        return $field ? $this->enrichField($field) : null;
    }

    public function getField($id){
        $field = $this->find($id);

        return $field ? $this->enrichField($field) : $field;
    }

    public function getName($id){
        $field = $this->find($id);

        return $field['name'];
    }

    public function enrichFields(array $fields): array
    {
        return array_map(fn ($field) => $this->enrichField($field), $fields);
    }

    public function enrichField(array $field): array
    {
        $db = $this->db;
        if (! $db->tableExists('services')) {
            return $field;
        }

        $service = null;
        if (! empty($field['service_id'])) {
            $service = $db->table('services')->where('id', $field['service_id'])->get()->getRowArray();
        }
        if (! $service && ! empty($field['service_type'])) {
            $service = $db->table('services')->where('code', $field['service_type'])->get()->getRowArray();
        }
        if (! $service) {
            return $field;
        }

        $field['service_id'] = $service['id'];
        $field['service_name'] = $service['name'];
        $field['service_type'] = $service['code'];
        $field['service_active'] = (int)($service['active'] ?? 1);
        $field['opening_time'] = $service['opening_time'];
        $field['closing_time'] = $service['closing_time'];
        $durationMinutes = (int)($service['duration_minutes'] ?? $service['minimum_duration_minutes'] ?? $field['block_minutes'] ?? 60);
        $intervalMinutes = (int)($service['slot_interval_minutes'] ?? $service['booking_interval_minutes'] ?? $durationMinutes);
        $field['duration_minutes'] = $durationMinutes;
        $field['slot_interval_minutes'] = $intervalMinutes;
        $field['block_minutes'] = $durationMinutes;
        $field['booking_interval_minutes'] = $intervalMinutes;
        $field['online_available'] = (int)($service['online_available'] ?? 1);
        $field['allows_quincho_addon'] = (int)($service['allows_quincho_addon'] ?? 1);
        $field['service_color'] = $service['color'] ?? '';
        $field['service_icon'] = $service['icon'] ?? '';
        $field['offer_active'] = (int)($service['offer_active'] ?? 0);
        $field['offer_text'] = $service['offer_text'] ?? '';
        $field['discount_type'] = $service['discount_type'] ?? 'percentage';
        $field['discount_value'] = (float)($service['discount_value'] ?? 0);
        $field['offer_start_date'] = $service['offer_start_date'] ?? null;
        $field['offer_end_date'] = $service['offer_end_date'] ?? null;

        if ($db->tableExists('service_prices')) {
            $price = $db->table('service_prices')
                ->where('service_id', $service['id'])
                ->where('active', 1)
                ->orderBy('id', 'DESC')
                ->get()
                ->getRowArray();
            if ($price && (float)($price['base_price'] ?? 0) > 0) {
                $field['value'] = (float)$price['base_price'];
                $field['charge_type'] = $price['charge_type'] ?? null;
                $field['deposit_price'] = $price['deposit_price'] ?? null;
            }
        }

        return $field;
    }
}
