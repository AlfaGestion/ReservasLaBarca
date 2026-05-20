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
    protected $allowedFields    = ['name', 'floor_type', 'sizes', 'ilumination', 'field_type', 'service_type', 'block_minutes', 'price_unit_label', 'roofed', 'value', 'ilumination_value', 'elements_rent', 'disabled'];

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

        // log_message('debug', 'CANCHAS', var_dump($fields));

        return $fields;
    }

    public function getByServiceType(string $serviceType)
    {
        return $this->where('disabled', 0)
            ->where('service_type', $serviceType)
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function getQuincho()
    {
        return $this->where('disabled', 0)
            ->where('service_type', 'quincho')
            ->orderBy('id', 'ASC')
            ->first();
    }

    public function getField($id){
        $field = $this->find($id);

        return $field;
    }

    public function getName($id){
        $field = $this->find($id);

        return $field['name'];
    }
}
